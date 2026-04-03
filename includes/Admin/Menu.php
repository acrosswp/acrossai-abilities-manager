<?php
/**
 * Admin menu registration.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {
	private const SCREEN_ID = 'tools_page_abilities-editor';

	public static function register(): void {
		add_filter( 'default_hidden_columns', array( __CLASS__, 'default_hidden_columns' ), 10, 2 );
		add_filter( 'manage_' . self::SCREEN_ID . '_columns', array( __CLASS__, 'screen_columns' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );

		$hook_suffix = add_submenu_page(
			'tools.php',
			esc_html__( 'Ability Overrides', 'abilities-editor' ),
			esc_html__( 'Ability Overrides', 'abilities-editor' ),
			'manage_options',
			'abilities-editor',
			array( __CLASS__, 'render_page' )
		);

		if ( false !== $hook_suffix ) {
			add_action( 'load-' . $hook_suffix, array( __CLASS__, 'configure_screen_options' ) );
		}
	}

	public static function configure_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Abilities per page', 'abilities-editor' ),
				'default' => 20,
				'option'  => 'abilities_editor_per_page',
			)
		);
	}

	public static function set_screen_option( $status, string $option, $value ) {
		if ( 'abilities_editor_per_page' !== $option ) {
			return $status;
		}

		return max( 1, (int) $value );
	}

	public static function screen_columns(): array {
		$table = new List_Table();

		return $table->get_columns();
	}


	public static function plugin_action_links( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = '<a href="' . esc_url( admin_url( 'tools.php?page=abilities-editor' ) ) . '">' . esc_html__( 'Settings', 'abilities-editor' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	public static function default_hidden_columns( array $hidden, $screen ): array {
		if ( ! is_object( $screen ) || empty( $screen->id ) || self::SCREEN_ID !== $screen->id ) {
			return $hidden;
		}

		$hidden[] = 'destructive';
		$hidden[] = 'idempotent';

		return array_values( array_unique( $hidden ) );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'abilities-editor' ) );
		}
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'Ability Overrides', 'abilities-editor' ) . '</h1>';
		self::render_notice();
		if ( in_array( $action, array( 'edit', 'view' ), true ) && '' !== $slug ) {
			Edit_Screen::render( $slug );
		} else {
			$table = new List_Table();
			$table->prepare_items();
			$table->render_stats_bar();
			?>
			<form method="get">
				<input type="hidden" name="page" value="abilities-editor" />
				<?php $provider = isset( $_GET['provider'] ) ? sanitize_text_field( wp_unslash( $_GET['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( 'all' !== $provider ) : ?>
					<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>" />
				<?php endif; ?>
				<?php $table->search_box( __( 'Search Abilities', 'abilities-editor' ), 'abilities-editor-search' ); ?>
				<?php $table->display(); ?>
			</form>
			<?php
		}
		echo '</div>';
	}


	private static function render_notice(): void {
		$notice = isset( $_GET['abe_notice'] ) ? sanitize_key( wp_unslash( $_GET['abe_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'saved'   => array( 'success', __( 'Override saved.', 'abilities-editor' ) ),
			'deleted' => array( 'success', __( 'Override reset.', 'abilities-editor' ) ),
			'noop'    => array( 'info', __( 'No override was saved because the values already match the default ability.', 'abilities-editor' ) ),
			'error'   => array( 'error', __( 'The requested action could not be completed.', 'abilities-editor' ) ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
	}
}
