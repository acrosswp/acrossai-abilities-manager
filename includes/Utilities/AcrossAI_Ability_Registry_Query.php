<?php
/**
 * Registry query utility — filters, sorts, paginates, and merges abilities.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;

// Self-import: AcrossAI_Protected_Abilities is in the same namespace.

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Canonical filter/sort/paginate logic for the abilities registry list.
 *
 * The REST controller MUST NOT inline this logic (RF-03).
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Registry_Query {

	/**
	 * Query, filter, sort, and paginate abilities from the registry.
	 *
	 * @since  0.1.0
	 * @param  array                   $params   Query parameters.
	 *   - string      $search      Optional full-text search term.
	 *   - string      $orderby     Field to sort by (slug|provider|source|status). Default 'slug'.
	 *   - string      $order       Sort direction (asc|desc). Default 'asc'.
	 *   - string      $source      Filter by source (plugin|theme|core|db).
	 *   - bool|null   $has_override Filter to only overridden or non-overridden abilities.
	 *   - int         $page        1-based page number. Default 1.
	 *   - int         $per_page    Items per page (1–100). Default 20.
	 * @param  AcrossAI_Sitewide_Query $db_query BerlinDB Query instance.
	 * @return array{abilities: array, total: int, pages: int}
	 */
	public static function query( array $params, AcrossAI_Sitewide_Query $db_query ): array {
		$search       = isset( $params['search'] ) ? (string) $params['search'] : '';
		$orderby      = isset( $params['orderby'] ) ? (string) $params['orderby'] : 'slug';
		$order        = isset( $params['order'] ) && 'desc' === strtolower( $params['order'] ) ? 'desc' : 'asc';
		$source       = isset( $params['source'] ) ? (string) $params['source'] : '';
		$has_override = isset( $params['has_override'] ) ? $params['has_override'] : null;
		$page         = isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1;
		$per_page     = isset( $params['per_page'] ) ? min( 100, max( 1, (int) $params['per_page'] ) ) : 20;

		// Fetch all registered abilities from the WordPress registry.
		$all_abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

		$results = array();

		foreach ( $all_abilities as $slug => $ability_data ) {
			// Normalize WP_Ability object to array (wp_get_abilities() returns objects in WP 6.9+).
			$ability_data = AcrossAI_Ability_Merger::normalize_registry( $ability_data );

			// Ensure slug is set — normalize_registry sets it from get_name(),
			// but fall back to the loop key for any bare array that omits 'slug'.
			if ( empty( $ability_data['slug'] ) ) {
				$ability_data['slug'] = $slug;
			}

			// Skip protected system abilities (hidden from REST endpoints and UI).
			if ( AcrossAI_Protected_Abilities::is_protected( $slug ) ) {
				continue;
			}

			// Retrieve any stored override for this ability.
			$override = $db_query->get_override_by_slug( $slug );

			// Merge registry + override into the Effective Ability shape.
			$merged = AcrossAI_Ability_Merger::merge( $ability_data, $override );

			// Detect source if not already set.
			if ( empty( $merged['source'] ) ) {
				$merged['source'] = AcrossAI_Ability_Source_Detector::detect( $ability_data );
			}

			// Apply source filter.
			if ( '' !== $source && $merged['source'] !== $source ) {
				continue;
			}

			// Apply has_override filter.
			if ( null !== $has_override && (bool) $merged['has_override'] !== (bool) $has_override ) {
				continue;
			}

			// Apply search filter (slug and provider).
			if ( '' !== $search ) {
				$search_lower = strtolower( $search );
				$slug_match   = false !== strpos( strtolower( $slug ), $search_lower );
				$prov_match   = false !== strpos( strtolower( (string) ( isset( $merged['provider'] ) ? $merged['provider'] : '' ) ), $search_lower );
				if ( ! $slug_match && ! $prov_match ) {
					continue;
				}
			}

			$results[] = $merged;
		}

		// Sort.
		$orderby_local = $orderby;
		$order_local   = $order;
		usort(
			$results,
			function ( $a, $b ) use ( $orderby_local, $order_local ) {
				$val_a = isset( $a[ $orderby_local ] ) ? (string) $a[ $orderby_local ] : '';
				$val_b = isset( $b[ $orderby_local ] ) ? (string) $b[ $orderby_local ] : '';
				$cmp   = strcmp( $val_a, $val_b );
				return 'desc' === $order_local ? -$cmp : $cmp;
			}
		);

		$total  = count( $results );
		$pages  = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;
		$offset = ( $page - 1 ) * $per_page;

		$paged = array_slice( $results, $offset, $per_page );

		return array(
			'abilities' => $paged,
			'total'     => $total,
			'pages'     => $pages,
		);
	}
}
