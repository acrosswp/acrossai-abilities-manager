<?php
/**
 * BerlinDB Query class for ability override records.
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
 * Provides CRUD operations for the acrossai_abilities_overwrite table.
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
	protected $table_name = 'acrossai_abilities_overwrite';

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
	 * @since  0.1.0
	 * @param  string $slug   Ability slug.
	 * @param  array  $fields Field values to save.
	 * @return bool
	 */
	public function save_override( string $slug, array $fields ): bool {
		// JSON-encode mcp_servers before passing to BerlinDB — the column is longtext and
		// BerlinDB does NOT auto-encode PHP arrays. Without this the DB receives "[Array]"
		// instead of valid JSON, and mcp_servers would be lost on read.
		if ( isset( $fields['mcp_servers'] ) && is_array( $fields['mcp_servers'] ) ) {
			$fields['mcp_servers'] = wp_json_encode( $fields['mcp_servers'] );
		}

		$existing = $this->get_override_by_slug( $slug );
		$now      = current_time( 'mysql', true );
		$user_id  = get_current_user_id();

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
}
