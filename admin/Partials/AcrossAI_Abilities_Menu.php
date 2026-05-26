<?php
/**
 * Custom Abilities submenu page.
 *
 * Registers and renders a dedicated submenu page for managing custom abilities.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.2.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Custom Abilities submenu page handler.
 *
 * Registers a submenu page under the Abilities Manager main menu.
 * Renders the React Abilities Manager component.
 *
 * @since 0.2.0
 */
class AcrossAI_Abilities_Menu {

	/**
	 * Singleton instance.
	 *
	 * @since 0.2.0
	 * @var AcrossAI_Abilities_Menu|null
	 */
	protected static $_instance = null;

	/**
	 * Hook suffix for this submenu page.
	 *
	 * Returned by add_submenu_page() and used to guard script/style enqueuing.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Get singleton instance.
	 *
	 * @since 0.2.0
	 * @return AcrossAI_Abilities_Menu
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.2.0
	 */
	private function __construct() {
		$this->hook_suffix = '';
	}

	/**
	 * Register the submenu page.
	 *
	 * Called on admin_menu hook to register the Custom Abilities submenu page
	 * under the Abilities Manager main menu.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register_submenu(): void {
		$this->hook_suffix = add_submenu_page(
			'acrossai-abilities-manager',
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),
			__( 'Custom Abilities', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-custom',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the Custom Abilities page.
	 *
	 * Outputs the page wrapper and React component mount point.
	 * SEC-010-01: Secondary capability check for defense-in-depth.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
		}
		?>
		<div class="wrap">
			<div id="acrossai-abilities-root"></div>
		</div>
		<?php
	}

	/**
	 * Get the hook suffix for this submenu page.
	 *
	 * Used to guard script/style enqueuing in Admin\Main.
	 *
	 * @since 0.2.0
	 * @return string Hook suffix or empty string if not yet registered.
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
