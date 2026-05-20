<?php
/**
 * Custom Ability Page Renderer
 *
 * Renders the Custom Abilities admin page with DataForm/DataViews containers.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      1.0.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * AcrossAI_Custom_Ability_Page class
 *
 * Singleton: Renders Custom Abilities admin page.
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Page {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var AcrossAI_Custom_Ability_Page
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 * @return AcrossAI_Custom_Ability_Page
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton pattern
	}

	/**
	 * Render admin page
	 *
	 * Displays the Custom Abilities admin interface.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() {
		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acrossai-abilities-manager' ) );
		}

		// Assets are enqueued via admin_enqueue_scripts hook
		// wp_localize_script is called in AcrossAI_Custom_Ability_Assets

		?>
		<div class="wrap acrossai-custom-abilities-admin">
			<div class="acrossai-page-header">
				<h1><?php esc_html_e( 'Custom Abilities', 'acrossai-abilities-manager' ); ?></h1>
				<div class="acrossai-breadcrumbs">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager' ) ); ?>">
						<?php esc_html_e( 'Abilities Manager', 'acrossai-abilities-manager' ); ?>
					</a>
					<span>&nbsp;&gt;&nbsp;</span>
					<?php esc_html_e( 'Custom Abilities', 'acrossai-abilities-manager' ); ?>
				</div>
			</div>

			<div class="acrossai-content">
				<!-- DataForm Container: Ability creation/editing form -->
				<div id="acrossai-ability-form-container"></div>

				<!-- DataViews Container: Abilities list/management table -->
				<div id="acrossai-abilities-list-container"></div>
			</div>
		</div>
		<?php
	}
}
