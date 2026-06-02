<?php
/**
 * The settings submenu page for the plugin.
 *
 * Provides the settings submenu page for the plugin and registers
 * the plugin settings using the WordPress Settings API.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Admin/Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The settings submenu page for the plugin.
 *
 * Defines the plugin settings submenu page and registers settings sections
 * and fields using the WordPress Settings API.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Admin/Partials
 * @since      0.1.0
 */
class SettingsMenu {

	/**
	 * Singleton instance.
	 *
	 * @since 0.1.0
	 * @var SettingsMenu|null
	 */
	protected static $instance = null;

	/**
	 * Returns the singleton instance of this class.
	 *
	 * @since 0.1.0
	 * @return self
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
	private function __construct() {}

	/**
	 * Registers the settings submenu page.
	 *
	 * Hooked to admin_menu.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_submenu(): void {
		add_submenu_page(
			'acrossai-abilities-manager',
			__( 'Settings', 'acrossai-abilities-manager' ),
			__( 'Settings', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Registers settings sections and fields via the WordPress Settings API.
	 *
	 * Hooked to admin_init.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_settings(): void {
		// Log retention option.
		register_setting(
			'acrossai_abilities_settings',
			'acrossai_abilities_log_retention_days',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		// Uninstall delete data option.
		register_setting(
			'acrossai_abilities_settings',
			'acrossai_abilities_uninstall_delete_data',
			array(
				'sanitize_callback' => array( $this, 'sanitize_uninstall_flag' ),
				'default'           => 0,
			)
		);

		// Per-page display option.
		register_setting(
			'acrossai_abilities_settings',
			'acrossai_abilities_per_page',
			array(
				'sanitize_callback' => array( $this, 'sanitize_per_page' ),
				'default'           => 20,
			)
		);

		// Section 0: Display settings.
		add_settings_section(
			'acrossai_display_settings_section',
			__( 'Display Settings', 'acrossai-abilities-manager' ),
			'__return_false',
			'acrossai-abilities-settings'
		);

		add_settings_field(
			'acrossai_abilities_per_page',
			__( 'Abilities per page', 'acrossai-abilities-manager' ),
			array( $this, 'render_per_page_field' ),
			'acrossai-abilities-settings',
			'acrossai_display_settings_section'
		);

		// Section 1: Log settings.
		add_settings_section(
			'acrossai_log_settings_section',
			__( 'Log Settings', 'acrossai-abilities-manager' ),
			'__return_false',
			'acrossai-abilities-settings'
		);

		add_settings_field(
			'acrossai_abilities_log_retention_days',
			__( 'Delete logs after (days)', 'acrossai-abilities-manager' ),
			array( $this, 'render_retention_field' ),
			'acrossai-abilities-settings',
			'acrossai_log_settings_section'
		);

		// Section 2: Uninstall settings.
		add_settings_section(
			'acrossai_uninstall_settings_section',
			__( 'Uninstall Settings', 'acrossai-abilities-manager' ),
			'__return_false',
			'acrossai-abilities-settings'
		);

		add_settings_field(
			'acrossai_abilities_uninstall_delete_data',
			__( 'Delete all data on uninstall', 'acrossai-abilities-manager' ),
			array( $this, 'render_uninstall_field' ),
			'acrossai-abilities-settings',
			'acrossai_uninstall_settings_section'
		);
	}

	/**
	 * Sanitizes the abilities per-page value.
	 *
	 * Accepts integers in [1, 200]; returns 20 for anything outside that range.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw submitted value.
	 * @return int
	 */
	public function sanitize_per_page( $value ): int {
		$int = absint( $value );
		return ( $int < 1 || $int > 200 ) ? 20 : $int;
	}

	/**
	 * Renders the abilities per-page number input field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_per_page_field(): void {
		$value = (int) get_option( 'acrossai_abilities_per_page', 20 );
		printf(
			'<input type="number" id="acrossai_abilities_per_page" name="acrossai_abilities_per_page" value="%s" min="1" max="200" step="1" /><p class="description">%s</p>',
			esc_attr( (string) $value ),
			esc_html__( 'Number of abilities shown per page. Default: 20. Min: 1. Max: 200.', 'acrossai-abilities-manager' )
		);
	}

	/**
	 * Sanitizes the uninstall delete data checkbox value.
	 *
	 * Returns 1 when the checkbox is checked, 0 when unchecked or absent.
	 * Browsers do not send unchecked checkboxes, so an absent value means 0.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw submitted value.
	 * @return int
	 */
	public function sanitize_uninstall_flag( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Renders the log retention days input field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_retention_field(): void {
		$value = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
		printf(
			'<input type="number" id="acrossai_abilities_log_retention_days" name="acrossai_abilities_log_retention_days" value="%s" min="0" step="1" /><p class="description">%s</p>',
			esc_attr( (string) $value ),
			esc_html__( 'Set to 0 to keep logs forever. If a number is entered, logs older than that many days will be automatically deleted.', 'acrossai-abilities-manager' )
		);
	}

	/**
	 * Renders the uninstall delete data checkbox field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_uninstall_field(): void {
		$checked = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', 0 );
		printf(
			'<label><input type="checkbox" id="acrossai_abilities_uninstall_delete_data" name="acrossai_abilities_uninstall_delete_data" value="1" %s /> %s</label><p class="description"><span style="color: #d63638;">%s</span></p>',
			checked( $checked, true, false ),
			esc_html__( 'Delete all data on uninstall', 'acrossai-abilities-manager' ),
			esc_html__( '⚠ Warning: When checked, uninstalling this plugin will permanently delete all custom database tables and plugin options. This cannot be undone.', 'acrossai-abilities-manager' )
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abilities Manager — Settings', 'acrossai-abilities-manager' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acrossai_abilities_settings' );
				do_settings_sections( 'acrossai-abilities-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
