<?php
/**
 * BerlinDB Query class for ability records.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database;

use BerlinDB\Database\Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Provides CRUD operations for the acrossai_abilities table.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Query extends Query {

	/**
	 * Schema class for this query.
	 *
	 * @var string
	 */
	protected $table_schema = AcrossAI_Sitewide_Schema::class;

	/**
	 * Row class for query results.
	 *
	 * @var string
	 */
	protected $item_shape = AcrossAI_Sitewide_Row::class;

	/**
	 * Table name (without WordPress table prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'acrossai_abilities';

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Query|null
	 */
	protected static $_instance = null;

	/**
	 * Private constructor — enforces singleton pattern.
	 *
	 * @since  0.1.0
	 */
	private function __construct() {
		parent::__construct();
	}

	/**
	 * Get the singleton instance of this query.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Query
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Retrieve an override row by ability slug.
	 *
	 * @since  0.1.0
	 * @param  string $slug Ability slug.
	 * @return AcrossAI_Sitewide_Row|null
	 */
	public function get_override_by_slug( string $slug ): ?AcrossAI_Sitewide_Row {
		$results = $this->query(
			array(
				'ability_slug' => $slug,
				'number'       => 1,
			)
		);

		if ( empty( $results ) || ! $results[0] instanceof AcrossAI_Sitewide_Row ) {
			return null;
		}

		return $results[0];
	}

	/**
	 * Insert or update an override record for the given slug.
	 *
	 * On INSERT: sets created_at and created_by.
	 * On UPDATE: sets updated_at and updated_by only — does NOT overwrite created_at/created_by (A1/A2).
	 *
	 * JSON field size/depth validation is the caller's responsibility.
	 *
	 * @since  0.1.0
	 * @param  string $slug   Ability slug.
	 * @param  array  $fields Field values to save.
	 * @return bool
	 */
	public function save_override( string $slug, array $fields ): bool {
		// Cast PHP booleans to integers for all tinyint tri-state columns.
		// $wpdb->insert/update auto-detects format: is_int() → %d, otherwise → %s.
		// PHP false is not an int, so without this cast it gets format %s → '' (empty
		// string). MySQL strict mode (default in MySQL 8 / MariaDB 10.2+) rejects '' for
		// a tinyint column with "Incorrect integer value", silently aborting the INSERT.
		// Casting false → 0 and true → 1 forces %d and stores the correct value.
		// null is intentionally left as null (SQL NULL = Inherit state).
		$tri_state_columns = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'show_in_mcp' );
		foreach ( $tri_state_columns as $col ) {
			if ( array_key_exists( $col, $fields ) && is_bool( $fields[ $col ] ) ) {
				$fields[ $col ] = (int) $fields[ $col ]; // true → 1, false → 0.
			}
		}

		// F2 enum guards — strip invalid enum values before BerlinDB write.
		// Placed after tri-state cast (FR-018: tri-state block must come first).
		if ( array_key_exists( 'status', $fields ) && null !== $fields['status'] ) {
			if ( ! in_array( $fields['status'], array( 'draft', 'publish' ), true ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'acrossai: invalid status value blocked before DB write' );
				unset( $fields['status'] );
			}
		}
		if ( array_key_exists( 'callback_type', $fields ) && null !== $fields['callback_type'] ) {
			if ( ! in_array( $fields['callback_type'], array( 'noop', 'filter_hook', 'wp_remote_post', 'php_code' ), true ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'acrossai: invalid callback_type value blocked before DB write' );
				unset( $fields['callback_type'] );
			}
		}

		// JSON-encode all registered JSON longtext fields before passing to BerlinDB.
		// BerlinDB does NOT auto-encode PHP arrays. Registry is extensible via
		// acrossai_abilities_json_fields filter (SC-005).
		// On encode failure, or if the encoded string exceeds 64 KB, store null.
		// TASK-SEC-001: This is a DB-layer size guard; caller-side validation is still
		// required before reaching this method (N4 advisory).
		$max_json_bytes = 65536; // 64 KB — consistent with php_code field limit (spec).
		foreach ( AcrossAI_Sitewide_Row::get_json_fields() as $json_field ) {
			if ( isset( $fields[ $json_field ] ) && is_array( $fields[ $json_field ] ) ) {
				$encoded = \wp_json_encode( $fields[ $json_field ] );
				if ( false === $encoded || strlen( $encoded ) > $max_json_bytes ) {
					$fields[ $json_field ] = null;
				} else {
					$fields[ $json_field ] = $encoded;
				}
			}
		}

		// ST-02 / LOW-03: Defense-in-depth re-validation at the BerlinDB boundary.
		// PHP do_action() passes $fields by value so hook consumers cannot alter
		// the caller's array, but this guard protects against unexpected future
		// refactoring where call order might change.
		if ( array_key_exists( 'mcp_type', $fields ) && null !== $fields['mcp_type'] ) {
			if ( ! in_array( $fields['mcp_type'], array( 'tool', 'resource', 'prompt' ), true ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'acrossai: invalid mcp_type value blocked before DB write' );
				unset( $fields['mcp_type'] );
			}
		}
		// At this point mcp_servers is null, a JSON-encoded string (from the encoding
		// step above), or an unexpected non-null non-string — the latter must be cleared.
		if ( array_key_exists( 'mcp_servers', $fields ) && null !== $fields['mcp_servers'] ) {
			if ( ! is_string( $fields['mcp_servers'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'acrossai: non-string mcp_servers blocked before DB write' );
				$fields['mcp_servers'] = null;
			}
		}

		$existing = $this->get_override_by_slug( $slug );
		$now      = \current_time( 'mysql', true );
		$user_id  = \get_current_user_id();

		if ( null === $existing ) {
			// INSERT path — add_item() returns the new integer ID on success or false.
			$fields['ability_slug'] = $slug;
			$fields['created_at']   = $now;
			$fields['created_by']   = $user_id;
			$fields['updated_at']   = $now;

			$result = $this->add_item( $fields );
			// Check: false = failure, 0 = invalid ID, positive int = success.
			return false !== $result && (int) $result > 0;
		}

		// UPDATE path — update_item() returns the updated item object or false.
		// First arg MUST be the integer primary key, NOT the slug string.
		$fields['updated_at'] = $now;
		$fields['updated_by'] = $user_id;

		// Explicitly do NOT set created_at or created_by on update (A1/A2).
		unset( $fields['created_at'], $fields['created_by'] );

		$result = $this->update_item( $existing->id, $fields );
		return false !== $result;
	}

	/**
	 * Delete an override record by ability slug.
	 *
	 * @since  0.1.0
	 * @param  string $slug Ability slug.
	 * @return bool True if a record was deleted, false otherwise.
	 */
	public function delete_override_by_slug( string $slug ): bool {
		$existing = $this->get_override_by_slug( $slug );
		if ( null === $existing ) {
			return false;
		}
		$result = $this->delete_item( $existing->id );
		return false !== $result;
	}

	/**
	 * Retrieve all override rows indexed by ability_slug.
	 *
	 * Passes number => 0 to BerlinDB which signals no LIMIT clause (unlimited rows).
	 * Returns an associative array keyed by ability_slug.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Row[]  Indexed by ability_slug string.
	 */
	public function get_all_overrides(): array {
		$results = $this->query( array( 'number' => 0 ) );
		$indexed = array();
		foreach ( $results as $row ) {
			if ( $row instanceof AcrossAI_Sitewide_Row ) {
				$indexed[ $row->ability_slug ] = $row;
			}
		}
		return $indexed;
	}

	/**
	 * Retrieve all ability rows matching the given source value.
	 *
	 * Uses number => 0 (BerlinDB unlimited query — NOT -1, which absint() converts
	 * to 1, silently limiting results to a single row).
	 *
	 * AUTHORIZATION CONTRACT (TASK-SEC-002):
	 * This is a raw DB-layer helper. It performs no capability check.
	 * Every caller that surfaces results to a HTTP response or admin screen
	 * MUST call current_user_can( 'manage_options' ) before invoking this method.
	 * Callers that skip this check create an unauthenticated data-disclosure path
	 * (OWASP A01:2025). REST controller permission_callback is the canonical gate.
	 *
	 * @since  0.1.0
	 * @param  string|null $source Source value to match (e.g. 'db', 'plugin', 'core').
	 *                             Returns empty array for null or empty string (FR-011).
	 * @return AcrossAI_Sitewide_Row[]
	 */
	public function by_source( ?string $source ): array {
		if ( null === $source || '' === $source ) {
			return array();
		}

		$results = $this->query(
			array(
				'source' => $source,
				'number' => 0,
			)
		);

		$rows = array();
		foreach ( $results as $row ) {
			if ( $row instanceof AcrossAI_Sitewide_Row ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}
