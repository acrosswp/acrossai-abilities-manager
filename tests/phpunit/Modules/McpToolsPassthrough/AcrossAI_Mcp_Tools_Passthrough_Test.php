<?php
/**
 * Tests: AcrossAI_Ability_Override_Processor::inject_mcp_tools().
 *
 * Tests target the mcp_adapter_init P20 action callback:
 *   inject_mcp_tools( mixed $adapter ): void
 *
 * Uses Reflection to pre-populate the static overrides cache, bypassing WordPress
 * DB/transient dependencies. Bootstrap shim is pending (T014 pre-existing gap) —
 * tests are correct but cannot execute until the shim is in place.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\McpToolsPassthrough;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Ability_Override_Processor;
use PHPUnit\Framework\TestCase;

/**
 * Verifies inject_mcp_tools() (mcp_adapter_init P20 / Reflection approach):
 * (a) No-op when adapter has no get_servers() (FR-009 guard).
 * (b) No-op when no abilities are opted in — early-return path (US2/FR-004).
 * (c) Opted-in slugs are registered via Reflection into McpServer::$component_registry.
 * (d) mcp_servers allowlist is respected per server (empty array = deny; allowlist = filtered).
 * (e) Servers without get_server_id() or without $component_registry are silently skipped.
 */
class AcrossAI_Mcp_Tools_Passthrough_Test extends TestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Pre-populate the static overrides cache via Reflection to skip WP DB/transient.
	 *
	 * @param array<string, object> $rows slug → object with pass_as_tool/mcp_servers props.
	 */
	private function setOverridesCache( array $rows ): void {
		$ref  = new \ReflectionClass( AcrossAI_Ability_Override_Processor::class );
		$prop = $ref->getProperty( 'overrides_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, $rows );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Reset static cache so tests don't bleed into each other.
		$ref  = new \ReflectionClass( AcrossAI_Ability_Override_Processor::class );
		$prop = $ref->getProperty( 'overrides_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Build a minimal row-like object with pass_as_tool and mcp_servers.
	 */
	private function makeRow( bool|null $pass_as_tool, array|null $mcp_servers = null ): object {
		return new class ( $pass_as_tool, $mcp_servers ) {
			public bool|null $pass_as_tool;
			public array|null $mcp_servers;
			public function __construct( bool|null $pat, array|null $ms ) {
				$this->pass_as_tool = $pat;
				$this->mcp_servers  = $ms;
			}
		};
	}

	/**
	 * Build a mock component registry that records register_tools() calls.
	 */
	private function makeRegistry(): object {
		return new class {
			public array $registered = array();
			public function register_tools( array $slugs ): void {
				foreach ( $slugs as $slug ) {
					$this->registered[] = $slug;
				}
			}
		};
	}

	/**
	 * Build a mock McpServer with a public $component_registry property.
	 * Reflection in inject_mcp_tools() reads this property directly.
	 */
	private function makeServer( string $server_id, object $registry ): object {
		return new class ( $server_id, $registry ) {
			public mixed $component_registry;
			public function __construct( private string $id, mixed $reg ) {
				$this->component_registry = $reg;
			}
			public function get_server_id(): string { return $this->id; }
		};
	}

	/**
	 * Build a mock McpAdapter with get_servers().
	 *
	 * @param object[] $servers
	 */
	private function makeAdapter( array $servers ): object {
		return new class ( $servers ) {
			public function __construct( private array $servers ) {}
			public function get_servers(): array { return $this->servers; }
		};
	}

	// -------------------------------------------------------------------------
	// Guard cases
	// -------------------------------------------------------------------------

	/**
	 * Adapter without get_servers() is a no-op — mcp-adapter absent guard (FR-009).
	 */
	public function test_no_op_when_adapter_lacks_get_servers(): void {
		$this->setOverridesCache( array(
			'my-ability' => $this->makeRow( true ),
		) );

		// Must not throw or produce a fatal error.
		AcrossAI_Ability_Override_Processor::inject_mcp_tools( new \stdClass() );
		$this->assertTrue( true );
	}

	/**
	 * No opted-in rows → early return before iterating servers (FR-004 / US2).
	 */
	public function test_no_op_when_no_opted_in_rows(): void {
		$registry = $this->makeRegistry();
		$adapter  = $this->makeAdapter( array( $this->makeServer( 'srv-1', $registry ) ) );

		$this->setOverridesCache( array(
			'my-ability' => $this->makeRow( null ), // pass_as_tool = null — not opted in.
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertSame( array(), $registry->registered, 'register_tools() must not be called when no rows are opted in' );
	}

	/**
	 * Empty cache → early return; register_tools() never called (FR-004).
	 */
	public function test_no_op_when_cache_is_empty(): void {
		$registry = $this->makeRegistry();
		$adapter  = $this->makeAdapter( array( $this->makeServer( 'srv-1', $registry ) ) );

		$this->setOverridesCache( array() );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertSame( array(), $registry->registered );
	}

	/**
	 * Server without get_server_id() is silently skipped.
	 */
	public function test_server_without_get_server_id_is_skipped(): void {
		$adapter = $this->makeAdapter( array( new \stdClass() ) ); // No get_server_id().

		$this->setOverridesCache( array(
			'my-tool' => $this->makeRow( true ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );
		$this->assertTrue( true );
	}

	/**
	 * Server without $component_registry property is silently skipped (Reflection guard).
	 */
	public function test_server_without_component_registry_is_skipped(): void {
		$serverNoRegistry = new class {
			public function get_server_id(): string { return 'srv-no-reg'; }
		};
		$adapter = $this->makeAdapter( array( $serverNoRegistry ) );

		$this->setOverridesCache( array(
			'my-tool' => $this->makeRow( true ),
		) );

		// ReflectionException path — must not throw; continues silently.
		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// Happy path — injection
	// -------------------------------------------------------------------------

	/**
	 * Opted-in slug is registered via Reflection into the server's component registry.
	 * AC check is fail-open (FR-011): no AC library in unit test → access granted.
	 */
	public function test_opted_in_slug_registered_in_component_registry(): void {
		$registry = $this->makeRegistry();
		$server   = $this->makeServer( 'srv-1', $registry );
		$adapter  = $this->makeAdapter( array( $server ) );

		$this->setOverridesCache( array(
			'core-get-env' => $this->makeRow( true ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertContains( 'core-get-env', $registry->registered, 'Opted-in slug must be registered via Reflection' );
	}

	/**
	 * Multiple opted-in slugs are all registered; non-opted-in slug is excluded.
	 */
	public function test_multiple_slugs_registered_non_opted_in_excluded(): void {
		$registry = $this->makeRegistry();
		$adapter  = $this->makeAdapter( array( $this->makeServer( 'srv-1', $registry ) ) );

		$this->setOverridesCache( array(
			'ability-a' => $this->makeRow( true ),
			'ability-b' => $this->makeRow( null ),  // Not opted in — must be excluded.
			'ability-c' => $this->makeRow( true ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertContains( 'ability-a', $registry->registered );
		$this->assertNotContains( 'ability-b', $registry->registered );
		$this->assertContains( 'ability-c', $registry->registered );
	}

	// -------------------------------------------------------------------------
	// mcp_servers allowlist
	// -------------------------------------------------------------------------

	/**
	 * mcp_servers = [] (explicit deny) blocks injection on all servers.
	 */
	public function test_mcp_servers_empty_array_blocks_all_servers(): void {
		$registry = $this->makeRegistry();
		$adapter  = $this->makeAdapter( array( $this->makeServer( 'srv-1', $registry ) ) );

		$this->setOverridesCache( array(
			'blocked-ability' => $this->makeRow( true, array() ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertSame( array(), $registry->registered, 'Empty mcp_servers must block injection on all servers' );
	}

	/**
	 * mcp_servers allowlist: slug injected on matching server only; blocked on non-matching.
	 */
	public function test_mcp_servers_allowlist_injects_only_on_matching_server(): void {
		$allowedReg = $this->makeRegistry();
		$blockedReg = $this->makeRegistry();
		$adapter    = $this->makeAdapter( array(
			$this->makeServer( 'srv-allowed', $allowedReg ),
			$this->makeServer( 'srv-blocked', $blockedReg ),
		) );

		$this->setOverridesCache( array(
			'my-tool' => $this->makeRow( true, array( 'srv-allowed' ) ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertContains( 'my-tool', $allowedReg->registered, 'Slug must be injected for the allowed server' );
		$this->assertSame( array(), $blockedReg->registered, 'Slug must NOT be injected for the non-allowlisted server' );
	}

	/**
	 * mcp_servers = null → all servers receive the injection.
	 */
	public function test_mcp_servers_null_injects_on_all_servers(): void {
		$reg1    = $this->makeRegistry();
		$reg2    = $this->makeRegistry();
		$adapter = $this->makeAdapter( array(
			$this->makeServer( 'srv-1', $reg1 ),
			$this->makeServer( 'srv-2', $reg2 ),
		) );

		$this->setOverridesCache( array(
			'global-tool' => $this->makeRow( true, null ),
		) );

		AcrossAI_Ability_Override_Processor::inject_mcp_tools( $adapter );

		$this->assertContains( 'global-tool', $reg1->registered );
		$this->assertContains( 'global-tool', $reg2->registered );
	}
}
