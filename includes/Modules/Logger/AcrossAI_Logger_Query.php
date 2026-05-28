<?php
/**
 * AcrossAI Logger Query Builder
 *
 * High-level query builder with filtering, sorting, pagination, and search.
 * All filtering/sorting logic is in this layer (AC-QUERY-LAYER-FILTERING).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Row;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Query builder for logs with all filtering/sorting logic
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Query {

	/**
	 * Singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @var AcrossAI_Logger_Query|null
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @return AcrossAI_Logger_Query
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor for singleton
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Get logs with filtering, sorting, and pagination
	 *
	 * All filtering/sorting happens in this method (AC-QUERY-LAYER-FILTERING).
	 * REST controller only extracts params and calls this method.
	 *
	 * @since 0.1.0
	 * @param array $args Query arguments:
	 *   - search (string): Filter by ability_slug partial match.
	 *   - source (string|array): Filter by source (comma-separated or array).
	 *   - status (string|array): Filter by status (comma-separated or array).
	 *   - ability_slug (string): Filter by exact slug.
	 *   - user_id (int): Filter by user_id.
	 *   - orderby (string): Column to sort by (default: 'created_at').
	 *   - order (string): Sort direction 'ASC' or 'DESC' (default: 'DESC').
	 *   - page (int): Page number (default: 1).
	 *   - per_page (int): Records per page (default: 20).
	 * @return array Result array with keys: 'logs', 'total', 'pages'
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		// Parse arguments.
		$defaults = array(
			'search'       => '',
			'source'       => '',
			'status'       => '',
			'ability_slug' => '',
			'user_id'      => 0,
			'orderby'      => 'created_at',
			'order'        => 'DESC',
			'page'         => 1,
			'per_page'     => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate and sanitize pagination.
		$page     = absint( $args['page'] );
		$per_page = absint( $args['per_page'] );

		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100; // Cap per_page to prevent abuse.
		}

		// Calculate LIMIT and OFFSET.
		$offset = ( $page - 1 ) * $per_page;

		// Build WHERE clauses (filtering logic).
		$where_clauses = array();
		$where_values  = array();

		// Search by ability_slug (partial match).
		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = 'ability_slug LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		// Filter by source (comma-separated or array).
		if ( ! empty( $args['source'] ) ) {
			$sources = is_array( $args['source'] ) ? $args['source'] : explode( ',', $args['source'] );
			$sources = array_map( 'sanitize_text_field', $sources );
			$sources = array_map( 'trim', $sources );
			$sources = array_filter( $sources );

			// Validate all sources (SEC-04: strict comparison).
			$valid_sources = array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' );
			$sources       = array_filter(
				$sources,
				function ( $s ) use ( $valid_sources ) {
					return in_array( $s, $valid_sources, true );
				}
			);

			if ( ! empty( $sources ) ) {
				$source_placeholders = implode( ',', array_fill( 0, count( $sources ), '%s' ) );
				$where_clauses[]     = "source IN ({$source_placeholders})";
				$where_values        = array_merge( $where_values, $sources );
			}
		}

		// Filter by status (comma-separated or array).
		if ( ! empty( $args['status'] ) ) {
			$statuses = is_array( $args['status'] ) ? $args['status'] : explode( ',', $args['status'] );
			$statuses = array_map( 'sanitize_text_field', $statuses );
			$statuses = array_map( 'trim', $statuses );
			$statuses = array_filter( $statuses );

			// Validate all statuses (SEC-04: strict comparison).
			$valid_statuses = array( 'success', 'error', 'permission_denied' );
			$statuses       = array_filter(
				$statuses,
				function ( $s ) use ( $valid_statuses ) {
					return in_array( $s, $valid_statuses, true );
				}
			);

			if ( ! empty( $statuses ) ) {
				$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				$where_clauses[]     = "status IN ({$status_placeholders})";
				$where_values        = array_merge( $where_values, $statuses );
			}
		}

		// Filter by exact ability_slug.
		if ( ! empty( $args['ability_slug'] ) ) {
			$where_clauses[] = 'ability_slug = %s';
			$where_values[]  = sanitize_text_field( $args['ability_slug'] );
		}

		// Filter by user_id.
		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[]  = (int) $args['user_id'];
		}

		// Build WHERE clause.
		$where_clause = '';
		if ( ! empty( $where_clauses ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Validate and sanitize orderby (whitelist).
		$valid_orderbys = array( 'ability_slug', 'source', 'user_id', 'status', 'duration_ms', 'created_at' );
		$orderby        = sanitize_key( $args['orderby'] );
		if ( ! in_array( $orderby, $valid_orderbys, true ) ) {
			$orderby = 'created_at';
		}

		// Validate order (must be ASC or DESC).
		$order = strtoupper( sanitize_key( $args['order'] ) );
		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'DESC';
		}

		// Build query parts.
		$table = $wpdb->base_prefix . 'acrossai_ability_logs';

		// Count total after filtering (for pagination header).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` {$where_clause}", $where_values )
		);

		// Build paginated SELECT query.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_sql = "SELECT * FROM `{$table}` {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";

		// Add pagination params to values.
		$final_values = array_merge( $where_values, array( $per_page, $offset ) );

		// Execute paginated query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $select_sql, $final_values )
		);

		// Map results to Row objects.
		$logs = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$logs[] = new AcrossAI_Ability_Logs_Row( $result );
			}
		}

		// Calculate pages count.
		$pages = (int) ceil( $total / $per_page );

		return array(
			'logs'  => $logs,
			'total' => $total,
			'pages' => $pages,
		);
	}
}
