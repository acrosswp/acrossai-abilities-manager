<?php
/**
 * BerlinDB Query class for ability execution logs.
 *
 * Provides low-level CRUD operations for the acrossai_ability_logs table.
 * High-level filtering/sorting is handled by AcrossAI_Logger_Query (Phase C).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Provides CRUD operations for the acrossai_ability_logs table.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Query extends Query {

	/**
	 * Schema class for this query.
	 *
	 * @var string
	 */
	protected $table_schema = AcrossAI_Ability_Logs_Schema::class;

	/**
	 * Row class for query results.
	 *
	 * @var string
	 */
	protected $item_shape = AcrossAI_Ability_Logs_Row::class;

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'acrossai_ability_logs';

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Logs_Query|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance of this query.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Logs_Query
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Insert a log entry via BerlinDB add_item().
	 *
	 * @since  0.1.0
	 * @param  array $data Associative array of column => value pairs.
	 * @return int|false Inserted row ID on success, false on failure.
	 */
	public function insert_log( array $data ) {
		$result = $this->add_item( $data );

		return ( false !== $result && (int) $result > 0 ) ? (int) $result : false;
	}

	/**
	 * Retrieve log rows matching the given BerlinDB query args.
	 *
	 * Filtering is the caller's responsibility — this method passes args directly
	 * to BerlinDB without modification (AC-QUERY-LAYER-FILTERING).
	 *
	 * @since  0.1.0
	 * @param  array $args BerlinDB query arguments.
	 * @return AcrossAI_Ability_Logs_Row[]
	 */
	public function get_logs( array $args = array() ): array {
		return $this->query( $args );
	}

	/**
	 * Retrieve a single log row by primary key.
	 *
	 * @since  0.1.0
	 * @param  int $id Row ID.
	 * @return AcrossAI_Ability_Logs_Row|null
	 */
	public function get_log_by_id( int $id ): ?AcrossAI_Ability_Logs_Row {
		$results = $this->query(
			array(
				'id'     => $id,
				'number' => 1,
			)
		);

		if ( empty( $results ) || ! $results[0] instanceof AcrossAI_Ability_Logs_Row ) {
			return null;
		}

		return $results[0];
	}

	/**
	 * Delete all log rows created before the given datetime.
	 *
	 * Used by the retention cleanup job (T016).
	 *
	 * @since  0.1.0
	 * @param  string $date Datetime string in 'Y-m-d H:i:s' format.
	 * @return int Number of rows deleted, 0 on failure or empty date.
	 */
	public function delete_logs_before_date( string $date ): int {
		global $wpdb;

		if ( empty( $date ) ) {
			return 0;
		}

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$table,
				$date
			)
		);

		return ( false !== $result ) ? (int) $result : 0;
	}

	/**
	 * Return the total count of log rows in the table.
	 *
	 * @since  0.1.0
	 * @return int
	 */
	public function count_logs(): int {
		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
		);
	}

	/**
	 * Return the full table name including the WordPress table prefix.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	private function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}
}
