<?php
/**
 * WordPress payment-attempt repository.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Persistence;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Support\Clock;
use DateTimeZone;
use InvalidArgumentException;
use wpdb;

/**
 * Stores payment attempts with unique evidence and optimistic writes.
 */
final class WordPressPaymentAttemptRepository implements PaymentAttemptRepository {
	/**
	 * Fully prefixed payment-attempt table.
	 *
	 * @var string
	 */
	private readonly string $table_name;

	/**
	 * Create a WordPress repository.
	 *
	 * @param wpdb  $database WordPress database connection.
	 * @param Clock $clock    Time source.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly Clock $clock
	) {
		$this->table_name = PaymentAttemptSchema::table_name( $this->database->prefix );
	}

	/**
	 * Insert a new payment attempt.
	 *
	 * @param PaymentAttempt $attempt Unpersisted attempt.
	 *
	 * @throws InvalidArgumentException                When the attempt is already persisted.
	 * @throws PaymentAttemptPersistenceException      When storage rejects the insert.
	 */
	public function add( PaymentAttempt $attempt ): PaymentAttempt {
		if ( null !== $attempt->id() ) {
			throw new InvalidArgumentException( 'A persisted payment attempt cannot be added again.' );
		}

		$now    = $this->now();
		$result = $this->database->insert(
			$this->table_name,
			$this->record( $attempt, $now, $now ),
			$this->record_formats()
		);

		if ( false === $result || $this->database->insert_id < 1 ) {
			throw new PaymentAttemptPersistenceException( 'The payment attempt could not be stored.' );
		}

		return $this->with_id( $attempt, $this->database->insert_id );
	}

	/**
	 * Find an attempt by its immutable public identifier.
	 *
	 * @param string $attempt_id Public attempt identifier.
	 */
	public function find_by_attempt_id( string $attempt_id ): ?PaymentAttempt {
		$prepared = $this->database->prepare(
			'SELECT * FROM %i WHERE attempt_id = %s LIMIT 1',
			$this->table_name,
			$attempt_id
		);

		return $this->find_one( $prepared );
	}

	/**
	 * Find an attempt by its immutable Daraja correlation identifier.
	 *
	 * @param string $checkout_request_id Daraja checkout request identifier.
	 */
	public function find_by_checkout_request_id( string $checkout_request_id ): ?PaymentAttempt {
		$prepared = $this->database->prepare(
			'SELECT * FROM %i WHERE checkout_request_id = %s LIMIT 1',
			$this->table_name,
			$checkout_request_id
		);

		return $this->find_one( $prepared );
	}

	/**
	 * Determine whether a receipt already belongs to another attempt.
	 *
	 * @param string   $receipt_number       M-Pesa receipt number.
	 * @param int|null $excluding_attempt_id Attempt allowed to own the receipt.
	 */
	public function receipt_exists( string $receipt_number, ?int $excluding_attempt_id = null ): bool {
		if ( null === $excluding_attempt_id ) {
			$prepared = $this->database->prepare(
				'SELECT COUNT(*) FROM %i WHERE receipt_number = %s',
				$this->table_name,
				strtoupper( $receipt_number )
			);
		} else {
			$prepared = $this->database->prepare(
				'SELECT COUNT(*) FROM %i WHERE receipt_number = %s AND id <> %d',
				$this->table_name,
				strtoupper( $receipt_number ),
				$excluding_attempt_id
			);
		}

		return (int) $this->database->get_var( $prepared ) > 0;
	}

	/**
	 * Save one optimistic state transition.
	 *
	 * @param PaymentAttempt $expected Previously loaded attempt.
	 * @param PaymentAttempt $updated  Versioned replacement attempt.
	 *
	 * @throws InvalidArgumentException           When the transition versions do not match.
	 * @throws ConcurrentAttemptUpdate             When another request won the write.
	 * @throws PaymentAttemptPersistenceException When storage rejects the update.
	 */
	public function save( PaymentAttempt $expected, PaymentAttempt $updated ): void {
		$id = $expected->id();

		if (
			null === $id
			|| $id !== $updated->id()
			|| $expected->attempt_id() !== $updated->attempt_id()
			|| $expected->version() + 1 !== $updated->version()
		) {
			throw new InvalidArgumentException( 'The payment attempt update is invalid.' );
		}

		$created_at = $this->created_at( $id );
		$result     = $this->database->update(
			$this->table_name,
			$this->record( $updated, $created_at, $this->now() ),
			array(
				'id'      => $id,
				'version' => $expected->version(),
			),
			$this->record_formats(),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			throw new PaymentAttemptPersistenceException( 'The payment attempt could not be updated.' );
		}

		if ( 1 !== $result ) {
			throw new ConcurrentAttemptUpdate( 'The payment attempt changed before it could be saved.' );
		}
	}

	/**
	 * Load one prepared query.
	 *
	 * @param string|null $prepared Prepared SQL statement.
	 *
	 * @throws PaymentAttemptPersistenceException When preparation or hydration fails.
	 */
	private function find_one( ?string $prepared ): ?PaymentAttempt {
		if ( null === $prepared ) {
			throw new PaymentAttemptPersistenceException( 'The payment attempt query could not be prepared.' );
		}

		$row = $this->database->get_row( $prepared, 'ARRAY_A' );

		if ( null === $row ) {
			return null;
		}

		if ( ! is_array( $row ) ) {
			throw new PaymentAttemptPersistenceException( 'The stored payment attempt is invalid.' );
		}

		return $this->hydrate( $row );
	}

	/**
	 * Load the immutable creation timestamp for an update.
	 *
	 * @param int $id Attempt database identifier.
	 *
	 * @throws PaymentAttemptPersistenceException When the stored timestamp is unavailable.
	 */
	private function created_at( int $id ): string {
		$prepared = $this->database->prepare(
			'SELECT created_at_gmt FROM %i WHERE id = %d LIMIT 1',
			$this->table_name,
			$id
		);
		$value    = null === $prepared ? null : $this->database->get_var( $prepared );

		if ( ! is_string( $value ) ) {
			throw new PaymentAttemptPersistenceException( 'The stored payment attempt is invalid.' );
		}

		return $value;
	}

	/**
	 * Convert a stored row into a domain object.
	 *
	 * @param array<string, mixed> $row Stored payment-attempt row.
	 *
	 * @throws PaymentAttemptPersistenceException When required storage fields are missing.
	 */
	private function hydrate( array $row ): PaymentAttempt {
		$required = array(
			'id',
			'attempt_id',
			'order_id',
			'amount',
			'phone_hash',
			'state',
			'poll_count',
			'version',
		);

		foreach ( $required as $column ) {
			if ( ! isset( $row[ $column ] ) || ! is_scalar( $row[ $column ] ) ) {
				throw new PaymentAttemptPersistenceException( 'The stored payment attempt is incomplete.' );
			}
		}

		return PaymentAttempt::restore(
			(int) $row['id'],
			(string) $row['attempt_id'],
			(int) $row['order_id'],
			new KesAmount( (string) $row['amount'] ),
			(string) $row['phone_hash'],
			AttemptState::from( (string) $row['state'] ),
			$this->nullable_string( $row, 'merchant_request_id' ),
			$this->nullable_string( $row, 'checkout_request_id' ),
			$this->nullable_string( $row, 'receipt_number' ),
			$this->nullable_string( $row, 'provider_result_code' ),
			$this->nullable_string( $row, 'failure_code' ),
			(int) $row['poll_count'],
			(int) $row['version']
		);
	}

	/**
	 * Build a full storage record.
	 *
	 * @param PaymentAttempt $attempt    Payment attempt.
	 * @param string         $created_at Immutable creation timestamp.
	 * @param string         $updated_at Latest update timestamp.
	 *
	 * @return array<string, int|string|null>
	 */
	private function record( PaymentAttempt $attempt, string $created_at, string $updated_at ): array {
		return array(
			'attempt_id'           => $attempt->attempt_id(),
			'order_id'             => $attempt->order_id(),
			'amount'               => $attempt->amount()->value(),
			'phone_hash'           => $attempt->phone_hash(),
			'state'                => $attempt->state()->value,
			'merchant_request_id'  => $attempt->merchant_request_id(),
			'checkout_request_id'  => $attempt->checkout_request_id(),
			'receipt_number'       => $attempt->receipt_number(),
			'provider_result_code' => $attempt->provider_result_code(),
			'failure_code'         => $attempt->failure_code(),
			'poll_count'           => $attempt->poll_count(),
			'version'              => $attempt->version(),
			'created_at_gmt'       => $created_at,
			'updated_at_gmt'       => $updated_at,
			'settled_at_gmt'       => $attempt->state()->is_settled() ? $updated_at : null,
		);
	}

	/**
	 * Return wpdb formats matching a full storage record.
	 *
	 * @return list<string>
	 */
	private function record_formats(): array {
		return array(
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
		);
	}

	/**
	 * Return a nullable string column.
	 *
	 * @param array<string, mixed> $row    Stored row.
	 * @param string               $column Column name.
	 */
	private function nullable_string( array $row, string $column ): ?string {
		$value = $row[ $column ] ?? null;

		return is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Attach the generated database identifier.
	 *
	 * @param PaymentAttempt $attempt Unpersisted attempt.
	 * @param int            $id      Generated database identifier.
	 */
	private function with_id( PaymentAttempt $attempt, int $id ): PaymentAttempt {
		return PaymentAttempt::restore(
			$id,
			$attempt->attempt_id(),
			$attempt->order_id(),
			$attempt->amount(),
			$attempt->phone_hash(),
			$attempt->state(),
			$attempt->merchant_request_id(),
			$attempt->checkout_request_id(),
			$attempt->receipt_number(),
			$attempt->provider_result_code(),
			$attempt->failure_code(),
			$attempt->poll_count(),
			$attempt->version()
		);
	}

	/**
	 * Return current UTC storage time.
	 */
	private function now(): string {
		return $this->clock->now()->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
