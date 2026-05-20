<?php
/**
 * Admin Menu Page for AcrossAI Abilities Manager
 *
 * Main admin page with interface for Abilities and Overrides management.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/Admin/Partials
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Menu class for admin page content
 *
 * @since 0.0.1
 */
class Menu {

	/**
	 * Plugin name
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @since 0.0.1
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties
	 *
	 * @since 0.0.1
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Add plugin menu page
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function main_menu() {
		add_menu_page(
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-manager',
			array( $this, 'contents' ),
			'dashicons-admin-tools',
			99
		);
	}

	/**
	 * Render admin page content
	 *
	 * Displays the main Abilities Manager interface.
	 * Execution Logs are available as a dedicated submenu page (Feature 006).
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function contents() {
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<!-- Main Abilities Manager React app -->
			<div id="acrossai-abilities-manager-root"></div>
		</div>
		<?php
	}
}
