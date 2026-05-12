<?php
/**
 * Admin page enqueue and render class for the Sitewide Ability Manager.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Admin\Partials;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles asset enqueueing and page rendering for the Sitewide Ability Manager admin page.
 *
 * @since 0.1.0
 */
class SitewideAbilityPage {

	/**
	 * The admin menu page slug this page is scoped to.
	 *
	 * @var string
	 */
	private $page_slug = 'acrossai-abilities-manager';

	/**
	 * Enqueue JavaScript and CSS assets for the sitewide ability manager page.
	 *
	 * @since  0.1.0
	 * @param  string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function do_enqueue_assets( string $hook_suffix ): void {
		// Only load on our plugin page.
		if ( false === strpos( $hook_suffix, $this->page_slug ) ) {
			return;
		}

		$plugin_dir = defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH' ) ? ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH : '';
		$plugin_url = defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL' ) ? ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL : '';

		$asset_file = $plugin_dir . 'build/js/sitewide.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			'acrossai-abilities-sitewide',
			$plugin_url . 'build/js/sitewide.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			'acrossai-abilities-sitewide',
			$plugin_url . 'build/css/sitewide.css',
			array(),
			$asset['version']
		);

		wp_enqueue_script( 'acrossai-abilities-sitewide' );
		wp_enqueue_style( 'acrossai-abilities-sitewide' );

		// Inline JS globals used by the React app.
		wp_add_inline_script(
			'acrossai-abilities-sitewide',
			'window.acrossaiAbilitiesSitewide = ' . wp_json_encode(
				array(
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'rest_url'        => get_rest_url(),
					'current_user_id' => get_current_user_id(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Output the React app mount point.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap acrossai-abilities-manager-wrap">
			<div id="acrossai-abilities-manager-root"></div>
		</div>
		<?php
	}
}
