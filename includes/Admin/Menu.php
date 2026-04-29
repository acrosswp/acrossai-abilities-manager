<?php
/**
 * Admin menu registration.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's admin menu entry under Tools.
 *
 * Handles submenu registration, screen options (per-page preference),
 * default hidden columns, and the plugin action links filter.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Menu {

	/**
	 * WordPress screen ID for the plugin's list page.
	 *
	 * Derived from the top-level menu slug (`acrossai-abilities-manager`),
	 * following WordPress's naming convention for top-level pages.
	 */
	private const SCREEN_ID = 'toplevel_page_acrossai-abilities-manager';

	/**
	 * Registers the submenu page, column filters, and the screen-option action.
	 *
	 * Attaches column-management filters before the submenu page is created so
	 * they are active by the time WordPress processes the screen-options form.
	 * The `load-{hook}` action is registered only when WordPress successfully
	 * added the submenu page.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'default_hidden_columns', array( __CLASS__, 'default_hidden_columns' ), 10, 2 );
		add_filter( 'manage_' . self::SCREEN_ID . '_columns', array( __CLASS__, 'screen_columns' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );

		$hook_suffix = add_menu_page(
			esc_html__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			esc_html__( 'Abilities Manager', 'acrossai-abilities-manager' ),
			'manage_options',
			'acrossai-abilities-manager',
			array( __CLASS__, 'render_page' ),
			'dashicons-superhero-alt',
			100
		);

		// Only register the screen-options and enqueue callbacks when the menu
		// page was actually added. add_menu_page() returns false when the
		// capability check fails, which would make hook registration unsafe.
		if ( false !== $hook_suffix ) {
			add_action( 'load-' . $hook_suffix, array( __CLASS__, 'configure_screen_options' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		}
	}

	/**
	 * Registers the per-page screen option shown in the Screen Options panel.
	 *
	 * Called via the `load-{hook}` action, which fires only on the plugin's
	 * own admin page, so the option appears exclusively in the right context.
	 *
	 * @return void
	 */
	public static function configure_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Abilities per page', 'acrossai-abilities-manager' ),
				'default' => 20,
				'option'  => 'acrossai_abilities_manager_per_page',
			)
		);
	}

	/**
	 * Enqueues the plugin's CSS and JS on the plugin's own admin page only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::SCREEN_ID !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'acrossai-abilities-manager-admin',
			ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ACROSSAI_ABILITIES_MANAGER_VERSION
		);

		wp_enqueue_script(
			'acrossai-abilities-manager-admin',
			ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ACROSSAI_ABILITIES_MANAGER_VERSION,
			true
		);
	}

	/**
	 * Saves the per-page screen option when the user changes it.
	 *
	 * WordPress fires the `set-screen-option` filter for every screen option
	 * save request. This callback ignores options that do not belong to this
	 * plugin so other screen options are not accidentally modified.
	 *
	 * @param mixed  $status Current filter value (returned unchanged for other options).
	 * @param string $option Option name being saved.
	 * @param mixed  $value  New value submitted by the user.
	 * @return mixed Sanitized integer value for the plugin's option, or $status for others.
	 */
	public static function set_screen_option( $status, string $option, $value ) {
		// Ignore screen options that do not belong to this plugin.
		if ( 'acrossai_abilities_manager_per_page' !== $option ) {
			return $status;
		}

		return max( 1, (int) $value );
	}

	/**
	 * Returns the column definitions for the plugin's list table.
	 *
	 * WordPress calls this via the `manage_{screen_id}_columns` filter to
	 * populate the Screen Options column-visibility checkboxes.
	 *
	 * @return array<string, string> Column ID => translated label pairs.
	 */
	public static function screen_columns(): array {
		$table = new List_Table();

		return $table->get_columns();
	}


	/**
	 * Prepends a Settings link to the plugin's action links on the Plugins page.
	 *
	 * Returns the unchanged $links array when the current user lacks the
	 * `manage_options` capability so the link is never shown to non-admins.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string> Links array with the Settings link prepended.
	 */
	public static function plugin_action_links( array $links ): array {
		// Do not expose the settings link to users without the required capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager' ) ) . '">' . esc_html__( 'Settings', 'acrossai-abilities-manager' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Defines the default hidden columns for the plugin's list screen.
	 *
	 * WordPress fires `default_hidden_columns` for every screen, so the method
	 * returns the unmodified $hidden array immediately for any screen that is
	 * not the plugin's own list page.
	 *
	 * @param array<int, string> $hidden  Columns currently set to be hidden by default.
	 * @param mixed              $screen  Current WP_Screen object (or an invalid value).
	 * @return array<int, string> Updated hidden columns list.
	 */
	public static function default_hidden_columns( array $hidden, $screen ): array {
		// Do not modify hidden-column defaults for any screen other than our own list page.
		if ( ! is_object( $screen ) || empty( $screen->id ) || self::SCREEN_ID !== $screen->id ) {
			return $hidden;
		}

		$hidden[] = 'destructive';
		$hidden[] = 'idempotent';

		return array_values( array_unique( $hidden ) );
	}

	/**
	 * Renders the full plugin admin page (list view or edit view).
	 *
	 * The method reads the `action` query parameter to decide which sub-view
	 * to render. When `action=edit` it resolves the target ability slug from
	 * either a numeric `id` parameter (database row ID) or a plain `slug`
	 * parameter, then delegates to Edit_Screen::render(). Any other action
	 * (or no action) renders the WP_List_Table view.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		// Deny access to users who do not have the manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'acrossai-abilities-manager' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug   = '';

		// Resolve the ability slug for the edit view from either a row ID or a raw slug.
		if ( 'edit' === $action ) {
			$raw_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $raw_id > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Prefer looking up by primary-key ID so renamed slugs still open correctly.
				$override = \AcrossAI_Abilities_Manager\Database\Repository::get_by_id( $raw_id );
				$slug     = is_array( $override ) ? $override['ability_slug'] : '';
			} elseif ( isset( $_GET['slug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Fall back to slug-based lookup for abilities that have no stored override yet.
				$slug = sanitize_text_field( wp_unslash( $_GET['slug'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Ability Manager', 'acrossai-abilities-manager' ) . '</h1>';
		self::render_notice();

		// Show the edit form when an action and a valid slug are both present;
		// fall back to the list table for all other cases.
		if ( 'edit' === $action && '' !== $slug ) {
			Edit_Screen::render( $slug );
		} else {
			$table = new List_Table();
			$table->prepare_items();
			$table->render_stats_bar();
			?>
			<form method="get">
				<input type="hidden" name="page" value="acrossai-abilities-manager" />
				<?php $provider = isset( $_GET['provider'] ) ? sanitize_text_field( wp_unslash( $_GET['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( 'all' !== $provider ) : ?>
					<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>" />
				<?php endif; ?>
				<?php $table->search_box( __( 'Search Abilities', 'acrossai-abilities-manager' ), 'acrossai-abilities-manager-search' ); ?>
				<?php $table->display(); ?>
			</form>
			<?php
		}

		echo '</div>';
	}


	/**
	 * Displays an admin notice based on the `aam_notice` query parameter.
	 *
	 * Only a whitelist of known notice keys produces output; any unknown or
	 * absent key causes the method to return early without printing anything.
	 * This prevents rendering arbitrary user-supplied strings as HTML.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$notice   = isset( $_GET['aam_notice'] ) ? sanitize_key( wp_unslash( $_GET['aam_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'saved'      => array( 'success', __( 'Override saved.', 'acrossai-abilities-manager' ) ),
			'deleted'    => array( 'success', __( 'Override reset.', 'acrossai-abilities-manager' ) ),
			'allowed'    => array( 'success', __( 'Ability allowed on this site.', 'acrossai-abilities-manager' ) ),
			'disallowed' => array( 'success', __( 'Ability disallowed on this site.', 'acrossai-abilities-manager' ) ),
			'noop'       => array( 'info', __( 'No override was saved because the values already match the default ability.', 'acrossai-abilities-manager' ) ),
			'error'      => array( 'error', __( 'The requested action could not be completed.', 'acrossai-abilities-manager' ) ),
		);

		// Bail silently when the notice key is absent or not in the whitelist.
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
	}
}
