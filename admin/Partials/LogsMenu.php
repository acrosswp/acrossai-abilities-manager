<?php
/**
 * Execution Logs submenu page.
 *
 * Registers and renders a dedicated submenu page for viewing ability execution logs.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Execution Logs submenu page handler.
 *
 * Registers a submenu page under the Abilities Manager main menu.
 * Renders the React LogsTable component for viewing execution logs.
 *
 * @since 0.1.0
 */
class LogsMenu {

	/**
	 * Singleton instance.
	 *
	 * @since 0.1.0
	 * @var LogsMenu|null
	 */
	protected static $instance = null;

	/**
	 * Hook suffix for this submenu page.
	 *
	 * Returned by add_submenu_page() and used to guard script/style enqueuing.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Get singleton instance.
	 *
	 * @since 0.1.0
	 * @return LogsMenu
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->hook_suffix = '';
	}

	/**
	 * Register the submenu page.
	 *
	 * Called on admin_menu hook to register the Logs submenu page
	 * under the Abilities Manager main menu.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_submenu(): void {
		$this->hook_suffix = add_submenu_page(
			'acrossai-abilities-manager',
			__( 'Execution Logs', 'acrossai-abilities-manager' ),
			__( 'Logs', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-logs',
			array( $this, 'render' )
		);
	}

	/**
	 * Render the Logs page.
	 *
	 * Outputs the page wrapper and React component mount point.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Execution Logs', 'acrossai-abilities-manager' ); ?></h1>
			<div id="acrossai-logs-container"></div>
		</div>
		<?php
	}

	/**
	 * Get the hook suffix for this submenu page.
	 *
	 * Used to guard script/style enqueuing in Admin\Main.
	 *
	 * @since 0.1.0
	 * @return string Hook suffix or empty string if not yet registered
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
