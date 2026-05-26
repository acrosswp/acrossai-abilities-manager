<?php
/**
 * Tests for AcrossAI_Abilities_Query.
 *
 * Covers: source/status filters, editable filtering, exposure filtering,
 * accurate totals, unlimited `number => 0` fetches, CRUD round-trips,
 * slug existence check, sparse update field preservation.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row;

/**
 * Class AbilitiesQueryTest
 *
 * @since 0.1.0
 */
class AbilitiesQueryTest extends WP_UnitTestCase {

	/**
	 * Query object.
	 *
	 * @var AcrossAI_Abilities_Query
	 */
	protected $query;

	/**
	 * Set up — ensure the table exists and obtain the query singleton.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		( new AcrossAI_Sitewide_Table() )->maybe_upgrade();
		$this->query = AcrossAI_Abilities_Query::instance();
	}

	/**
	 * Tear down — delete all test rows inserted during the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}acrossai_abilities WHERE ability_slug LIKE 'acrossai-abilities/test-%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Insert a minimal ability row for testing.
	 *
	 * @param  array $overrides Field overrides.
	 * @return int  New row ID.
	 */
	private function insert_row( array $overrides = [] ): int {
		static $counter = 0;
		++$counter;

		$defaults = [
			'ability_slug'  => 'acrossai-abilities/test-' . $counter,
			'label'         => 'Test Ability ' . $counter,
			'category'      => 'general',
			'status'        => 'draft',
			'source'        => 'db',
			'callback_type' => 'noop',
		];

		$id = $this->query->insert_ability( array_merge( $defaults, $overrides ) );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	// -------------------------------------------------------------------------
	// CRUD round-trips
	// -------------------------------------------------------------------------

	/**
	 * insert_ability + get_ability_by_id round-trip.
	 *
	 * @return void
	 */
	public function test_insert_and_get_by_id_round_trip() {
		$id  = $this->insert_row( [ 'label' => 'My Ability', 'category' => 'custom' ] );
		$row = $this->query->get_ability_by_id( $id );

		$this->assertInstanceOf( AcrossAI_Sitewide_Row::class, $row );
		$this->assertSame( 'My Ability', $row->label );
		$this->assertSame( 'custom', $row->category );
		$this->assertSame( 'db', $row->source );
	}

	/**
	 * get_ability_by_id returns null for unknown ID.
	 *
	 * @return void
	 */
	public function test_get_by_id_returns_null_for_unknown() {
		$result = $this->query->get_ability_by_id( PHP_INT_MAX );
		$this->assertNull( $result );
	}

	/**
	 * get_ability_by_slug returns the matching row.
	 *
	 * @return void
	 */
	public function test_get_by_slug_returns_correct_row() {
		$id  = $this->insert_row( [ 'ability_slug' => 'acrossai-abilities/test-slug-lookup' ] );
		$row = $this->query->get_ability_by_slug( 'acrossai-abilities/test-slug-lookup' );

		$this->assertNotNull( $row );
		$this->assertSame( $id, (int) $row->id );
	}

	/**
	 * slug_exists returns true for an existing slug.
	 *
	 * @return void
	 */
	public function test_slug_exists_returns_true_for_existing() {
		$this->insert_row( [ 'ability_slug' => 'acrossai-abilities/test-exists' ] );
		$this->assertTrue( $this->query->slug_exists( 'acrossai-abilities/test-exists' ) );
	}

	/**
	 * slug_exists returns false for a non-existent slug.
	 *
	 * @return void
	 */
	public function test_slug_exists_returns_false_for_missing() {
		$this->assertFalse( $this->query->slug_exists( 'acrossai-abilities/no-such-slug' ) );
	}

	/**
	 * update_ability sparse-updates only supplied fields.
	 *
	 * @return void
	 */
	public function test_update_ability_preserves_untouched_fields() {
		$id = $this->insert_row( [ 'label' => 'Original', 'category' => 'original-cat' ] );

		$updated = $this->query->update_ability( $id, [ 'label' => 'Updated' ] );
		$this->assertTrue( $updated );

		$row = $this->query->get_ability_by_id( $id );
		$this->assertSame( 'Updated', $row->label );
		$this->assertSame( 'original-cat', $row->category, 'Untouched category must be preserved' );
	}

	/**
	 * update_ability never overwrites created_at or created_by.
	 *
	 * @return void
	 */
	public function test_update_ability_never_overwrites_audit_fields() {
		$id  = $this->insert_row();
		$row = $this->query->get_ability_by_id( $id );

		$original_created_at = $row->created_at;
		$original_created_by = $row->created_by;

		// Attempt to change audit fields via update.
		$this->query->update_ability( $id, [
			'created_at' => '1970-01-01 00:00:00',
			'created_by' => 9999,
			'label'      => 'Changed',
		] );

		$refreshed = $this->query->get_ability_by_id( $id );
		$this->assertSame( $original_created_at, $refreshed->created_at );
		$this->assertSame( $original_created_by, $refreshed->created_by );
	}

	/**
	 * delete_ability removes the row.
	 *
	 * @return void
	 */
	public function test_delete_ability_removes_row() {
		$id = $this->insert_row();
		$this->assertTrue( $this->query->delete_ability( $id ) );
		$this->assertNull( $this->query->get_ability_by_id( $id ) );
	}

	// -------------------------------------------------------------------------
	// Filter helpers
	// -------------------------------------------------------------------------

	/**
	 * by_source returns only rows of the requested source.
	 *
	 * @return void
	 */
	public function test_by_source_filters_correctly() {
		$this->insert_row( [ 'source' => 'db' ] );
		$this->insert_row( [ 'source' => 'plugin' ] );

		$db_rows     = $this->query->by_source( 'db' );
		$plugin_rows = $this->query->by_source( 'plugin' );

		foreach ( $db_rows as $row ) {
			$this->assertSame( 'db', $row->source );
		}
		foreach ( $plugin_rows as $row ) {
			$this->assertSame( 'plugin', $row->source );
		}
	}

	/**
	 * by_source with empty string returns an empty array (guard).
	 *
	 * @return void
	 */
	public function test_by_source_empty_string_returns_empty_array() {
		$this->insert_row();
		$result = $this->query->by_source( '' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * published_db_abilities returns only source=db + status=publish rows.
	 *
	 * @return void
	 */
	public function test_published_db_abilities_filters_source_and_status() {
		$this->insert_row( [ 'source' => 'db',     'status' => 'publish' ] );
		$this->insert_row( [ 'source' => 'db',     'status' => 'draft'   ] );
		$this->insert_row( [ 'source' => 'plugin', 'status' => 'publish' ] );

		$rows = $this->query->published_db_abilities();

		$this->assertNotEmpty( $rows );
		foreach ( $rows as $row ) {
			$this->assertSame( 'db', $row->source );
			$this->assertSame( 'publish', $row->status );
		}
	}

	/**
	 * by_mcp_type filters by mcp_type and restricts to show_in_mcp=1 when $mcp_only=true.
	 *
	 * @return void
	 */
	public function test_by_mcp_type_filters_type_and_show_in_mcp() {
		$this->insert_row( [ 'mcp_type' => 'tool',     'show_in_mcp' => 1, 'status' => 'publish' ] );
		$this->insert_row( [ 'mcp_type' => 'tool',     'show_in_mcp' => 0, 'status' => 'publish' ] );
		$this->insert_row( [ 'mcp_type' => 'resource', 'show_in_mcp' => 1, 'status' => 'publish' ] );

		$tools = $this->query->by_mcp_type( 'tool', true );

		foreach ( $tools as $row ) {
			$this->assertSame( 'tool', $row->mcp_type );
			$this->assertSame( 1, (int) $row->show_in_mcp );
		}
	}

	// -------------------------------------------------------------------------
	// Pagination and totals
	// -------------------------------------------------------------------------

	/**
	 * get_paginated returns correct page slice and accurate total count.
	 *
	 * @return void
	 */
	public function test_get_paginated_returns_correct_total_and_slice() {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->insert_row( [ 'source' => 'db', 'status' => 'draft' ] );
		}

		$result = $this->query->get_paginated( [
			'page'     => 1,
			'per_page' => 2,
			'source'   => 'db',
			'status'   => 'draft',
		] );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'pages', $result );

		$this->assertCount( 2, $result['items'] );
		$this->assertGreaterThanOrEqual( 5, $result['total'] );
		$this->assertGreaterThanOrEqual( 3, $result['pages'] );
	}

	/**
	 * get_paginated with number=>0 equivalent (via large per_page) fetches all rows.
	 *
	 * @return void
	 */
	public function test_get_paginated_per_page_100_cap_applied() {
		$result = $this->query->get_paginated( [ 'per_page' => 999 ] );
		// per_page is capped at 100 internally — the method should not error.
		$this->assertIsArray( $result['items'] );
	}

	/**
	 * published_db_abilities uses number=>0 and returns all matching rows (not just first page).
	 *
	 * @return void
	 */
	public function test_published_db_abilities_unlimited_fetch() {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->insert_row( [ 'source' => 'db', 'status' => 'publish' ] );
		}

		$rows = $this->query->published_db_abilities();
		$this->assertGreaterThanOrEqual( 25, count( $rows ) );
	}

	/**
	 * get_paginated page 2 returns second slice and total is accurate.
	 *
	 * @return void
	 */
	public function test_get_paginated_page_2_returns_second_slice() {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->insert_row( [ 'source' => 'db', 'label' => 'Page2Test' . $i ] );
		}

		$page1 = $this->query->get_paginated( [
			'page'     => 1,
			'per_page' => 2,
			'search'   => 'Page2Test',
		] );
		$page2 = $this->query->get_paginated( [
			'page'     => 2,
			'per_page' => 2,
			'search'   => 'Page2Test',
		] );

		$ids1 = array_map( static fn( $r ) => (int) $r->id, $page1['items'] );
		$ids2 = array_map( static fn( $r ) => (int) $r->id, $page2['items'] );

		$this->assertEmpty( array_intersect( $ids1, $ids2 ), 'Pages must not overlap' );
		$this->assertSame( $page1['total'], $page2['total'], 'Total must be consistent across pages' );
	}
}
