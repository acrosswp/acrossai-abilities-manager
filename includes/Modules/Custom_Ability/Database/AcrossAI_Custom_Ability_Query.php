<?php
/**
 * BerlinDB Query builder for custom abilities
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Database
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Custom_Abilities;

/**
 * Class AcrossAI_Custom_Ability_Query
 *
 * Query builder for filtering, searching, and retrieving custom abilities.
 * Query layer is single source of truth for filtering (Memory AC-QUERY-LAYER-FILTERING).
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Query extends Query {

	/**
	 * Name of the index to select.
	 *
	 * @since 0.0.1
	 * @var string
	 */
	protected $index = 'id';

	/**
	 * Filter by ability slug.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug (e.g., "custom/my-ability").
	 * @return self For method chaining.
	 */
	public function by_slug( $slug = '' ) {
		return $this->where( 'ability_slug', $slug );
	}

	/**
	 * Filter to only enabled abilities.
	 *
	 * @since 0.0.1
	 * @return self For method chaining.
	 */
	public function enabled_only() {
		return $this->where( 'enabled', 1 );
	}

	/**
	 * Filter by category.
	 *
	 * @since 0.0.1
	 * @param string $category Category name.
	 * @return self For method chaining.
	 */
	public function by_category( $category = '' ) {
		return $this->where( 'category', $category );
	}

	/**
	 * Search by label, description, or slug.
	 *
	 * Filters out protected namespace prefixes (Memory DEC-PROTECTED-SLUGS-PATTERN).
	 * Query layer is single source of truth (Memory AC-QUERY-LAYER-FILTERING).
	 *
	 * @since 0.0.1
	 * @param string $search Search term.
	 * @return self For method chaining.
	 */
	public function search( $search = '' ) {
		if ( empty( $search ) ) {
			return $this;
		}

		$search = sanitize_text_field( $search );

		// Use LIKE for multiple columns
		$this->where_or( 'ability_slug', 'LIKE', '%' . $search . '%' );
		$this->where_or( 'label', 'LIKE', '%' . $search . '%' );
		$this->where_or( 'description', 'LIKE', '%' . $search . '%' );

		// Filter out protected prefixes (Memory DEC-PROTECTED-SLUGS-PATTERN)
		$protected_prefixes = AcrossAI_Protected_Custom_Abilities::get_protected_prefixes( 'custom_abilities' );
		foreach ( $protected_prefixes as $prefix ) {
			$this->where_not( 'ability_slug', 'LIKE', $prefix . '/%' );
		}

		/**
		 * Customize query layer filtering.
		 *
		 * @since 0.0.1
		 * @param array  $query_args Current query arguments.
		 * @param string $context    Context (e.g., 'list', 'read', 'mcp').
		 */
		do_action( 'acrossai_custom_ability_query_filters', $this->query, 'search' );

		return $this;
	}

	/**
	 * Set up pagination.
	 *
	 * @since 0.0.1
	 * @param int $per_page Items per page.
	 * @param int $page     Page number (1-based).
	 * @return self For method chaining.
	 */
	public function with_pagination( $per_page = 20, $page = 1 ) {
		$per_page = absint( $per_page );
		$page     = absint( $page );

		// Clamp per_page to reasonable range
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );

		$this->limit( $per_page );
		$this->offset( ( $page - 1 ) * $per_page );

		return $this;
	}

	/**
	 * Order results by column.
	 *
	 * @since 0.0.1
	 * @param string $by    Column name (slug, label, created_at, updated_at).
	 * @param string $order Sort order (asc, desc).
	 * @return self For method chaining.
	 */
	public function order_by( $by = 'id', $order = 'ASC' ) {
		$allowed_columns = array( 'id', 'ability_slug', 'label', 'created_at', 'updated_at', 'enabled', 'category' );
		$by              = in_array( $by, $allowed_columns, true ) ? $by : 'id';
		$order           = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

		$this->orderby( $by, $order );

		return $this;
	}

	/**
	 * Get results from query.
	 *
	 * @since 0.0.1
	 * @return array Array of AcrossAI_Custom_Ability_Row objects.
	 */
	public function get() {
		return parent::get( 'results' );
	}
}
