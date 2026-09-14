<?php
/**
 * WordPress manual-verification audit repository.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Persistence;

use DarajaMpesa\Application\ManualVerificationAudit;
use DarajaMpesa\Application\ManualVerificationRequest;
use DarajaMpesa\Support\Clock;
use DateTimeZone;
use InvalidArgumentException;
use wpdb;

/**
 * Appends privacy-bounded administrator verification records.
 */
final class WordPressManualVerificationAudit implements ManualVerificationAudit {
	/**
	 * Fully prefixed audit table.
	 *
	 * @var string
	 */
	private readonly string $table_name;

	/**
	 * Create a WordPress audit repository.
	 *
	 * @param wpdb  $database WordPress database connection.
	 * @param Clock $clock    Time source.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly Clock $clock
	) {
		$this->table_name = PaymentAttemptSchema::audit_table_name( $this->database->prefix );
	}

	/**
	 * Record one immutable verification outcome.
	 *
	 * @param ManualVerificationRequest $request Verification evidence.
	 * @param string                    $outcome Stable bounded outcome.
	 *
	 * @throws InvalidArgumentException               When the outcome is malformed.
	 * @throws PaymentAttemptPersistenceException     When storage rejects the audit.
	 */
	public function record( ManualVerificationRequest $request, string $outcome ): void {
		if ( 1 !== preg_match( '/^[a-z_]{3,64}$/', $outcome ) ) {
			throw new InvalidArgumentException( 'The manual verification outcome is invalid.' );
		}

		$result = $this->database->insert(
			$this->table_name,
			array(
				'attempt_id'      => $request->attempt_id(),
				'order_id'        => $request->order_id(),
				'operator_id'     => $request->operator_id(),
				'amount'          => $request->amount()->value(),
				'receipt_hash'    => hash( 'sha256', $request->receipt() ),
				'evidence_source' => $request->evidence_source(),
				'reason'          => $request->reason(),
				'outcome'         => $outcome,
				'created_at_gmt'  => $this->clock
					->now()
					->setTimezone( new DateTimeZone( 'UTC' ) )
					->format( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			throw new PaymentAttemptPersistenceException( 'The manual verification audit could not be stored.' );
		}
	}
}
