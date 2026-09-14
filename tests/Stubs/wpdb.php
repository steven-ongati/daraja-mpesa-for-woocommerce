<?php
/**
 * Minimal wpdb test stub.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital -- Matches the WordPress core class.
/**
 * Records repository database operations without a WordPress runtime.
 */
class wpdb {
	/**
	 * Test table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Generated insert identifier.
	 *
	 * @var int
	 */
	public int $insert_id = 0;

	/**
	 * Latest inserted data.
	 *
	 * @var array<string, mixed>
	 */
	public array $inserted_data = array();

	/**
	 * Latest updated data.
	 *
	 * @var array<string, mixed>
	 */
	public array $updated_data = array();

	/**
	 * Latest update predicate.
	 *
	 * @var array<string, mixed>
	 */
	public array $updated_where = array();

	/**
	 * Configured update result.
	 *
	 * @var int|false
	 */
	public int|false $update_result = 1;

	/**
	 * Configured row result.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $row_result = null;

	/**
	 * Configured scalar result.
	 *
	 * @var int|string|null
	 */
	public int|string|null $var_result = null;

	/**
	 * Record an insert operation.
	 *
	 * @param string               $table  Table name.
	 * @param array<string, mixed> $data   Inserted data.
	 * @param array<string>        $format Value formats.
	 */
	public function insert( string $table, array $data, array $format ): int|false {
		unset( $table, $format );

		$this->inserted_data = $data;
		$this->insert_id     = 41;

		return 1;
	}

	/**
	 * Record an update operation.
	 *
	 * @param string               $table        Table name.
	 * @param array<string, mixed> $data         Updated data.
	 * @param array<string, mixed> $where        Update predicate.
	 * @param array<string>        $format       Value formats.
	 * @param array<string>        $where_format Predicate formats.
	 */
	public function update(
		string $table,
		array $data,
		array $where,
		array $format,
		array $where_format
	): int|false {
		unset( $table, $format, $where_format );

		$this->updated_data  = $data;
		$this->updated_where = $where;

		return $this->update_result;
	}

	/**
	 * Return a stable prepared-query marker.
	 *
	 * @param string $query Query template.
	 * @param mixed  ...$args Query values.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		return $query . '|' . count( $args );
	}

	/**
	 * Return the configured row.
	 *
	 * @param string $query  Prepared query.
	 * @param string $output Output mode.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $query, string $output ): ?array {
		unset( $query, $output );

		return $this->row_result;
	}

	/**
	 * Return a configured scalar or creation timestamp.
	 *
	 * @param string $query Prepared query.
	 */
	public function get_var( string $query ): int|string|null {
		if ( str_contains( $query, 'created_at_gmt' ) ) {
			return '2026-01-01 00:00:00';
		}

		return $this->var_result;
	}
}
// phpcs:enable
