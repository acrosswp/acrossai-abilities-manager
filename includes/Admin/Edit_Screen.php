<?php
/**
 * Edit screen rendering and actions.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Admin;

use Abilities_Editor\Database\Repository;

defined( 'ABSPATH' ) || exit;

class Edit_Screen {
	public static function handle_actions(): void {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'abilities-editor' !== $page ) {
			return;
		}
		$action = isset( $_REQUEST['abe_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['abe_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'save' === $action && 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			self::save();
		}
		if ( 'delete' === $action ) {
			self::delete();
		}
	}

	public static function render( string $slug ): void {
		$slug = sanitize_text_field( $slug );
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'edit'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $slug ) : null;
		$override = Repository::get_by_slug( $slug );
		if ( ! $ability && ! $override ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Ability not found.', 'abilities-editor' ) . '</p></div>';
			return;
		}
		$provider      = is_array( $override ) && ! empty( $override['provider'] ) ? (string) $override['provider'] : self::detect_provider( $slug );
		$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
		$category      = self::ability_category_label( $category_slug );
		$view_only     = 'view' === $action;
		$values        = self::resolved_values( $override, $ability );

		if ( is_array( $override ) && $ability instanceof \WP_Ability && array() === self::build_override_values( $values, $ability ) ) {
			Repository::delete( $slug );
			$override      = null;
			$provider      = self::detect_provider( $slug );
			$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
			$category      = self::ability_category_label( $category_slug );
			$values        = self::resolved_values( null, $ability );
		}

		$back_url = admin_url( 'tools.php?page=abilities-editor' );
		$edit_url = add_query_arg( array( 'page' => 'abilities-editor', 'action' => 'edit', 'slug' => $slug ), admin_url( 'tools.php' ) );
		?>
		<p>
			<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Back to List', 'abilities-editor' ); ?></a>
			<?php if ( $view_only ) : ?>
				<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary"><?php esc_html_e( 'Edit Override', 'abilities-editor' ); ?></a>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=abilities-editor' ) ); ?>">
			<input type="hidden" name="page" value="abilities-editor" />
			<input type="hidden" name="abe_action" value="save" />
			<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
			<?php wp_nonce_field( 'abe_save_meta_' . $slug, 'abe_meta_nonce' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><?php esc_html_e( 'Ability Slug', 'abilities-editor' ); ?></th><td><input type="text" class="regular-text" value="<?php echo esc_attr( $slug ); ?>" readonly /></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Provider', 'abilities-editor' ); ?></th><td><input type="text" class="regular-text" value="<?php echo esc_attr( $provider ); ?>" readonly /></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Category', 'abilities-editor' ); ?></th><td><?php echo wp_kses_post( self::render_category_value( $category, $category_slug ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Readonly', 'abilities-editor' ); ?></th><td><?php self::select( 'readonly', $values['readonly'], $view_only ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Destructive', 'abilities-editor' ); ?></th><td><?php self::select( 'destructive', $values['destructive'], $view_only ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Idempotent', 'abilities-editor' ); ?></th><td><?php self::select( 'idempotent', $values['idempotent'], $view_only ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Show in REST', 'abilities-editor' ); ?></th><td><label><input type="checkbox" name="show_in_rest" value="1" <?php checked( (bool) $values['show_in_rest'] ); ?> <?php disabled( $view_only ); ?> /> <?php esc_html_e( 'Expose in REST.', 'abilities-editor' ); ?></label></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'MCP Public', 'abilities-editor' ); ?></th><td><label><input type="checkbox" id="abe-mcp-public" name="mcp_public" value="1" <?php checked( (bool) $values['mcp_public'] ); ?> <?php disabled( $view_only ); ?> /> <?php esc_html_e( 'Expose publicly to MCP clients.', 'abilities-editor' ); ?></label></td></tr>
			<tr id="abe-mcp-type-row"><th scope="row"><?php esc_html_e( 'MCP Type', 'abilities-editor' ); ?></th><td><?php self::mcp_type_select( (string) $values['mcp_type'], $view_only ); ?></td></tr>
			</tbody></table>
			<?php if ( ! $view_only ) : ?>
				<p class="submit">
					<button type="submit" name="abe_save_target" value="stay" class="button button-primary"><?php esc_html_e( 'Save', 'abilities-editor' ); ?></button>
					<button type="submit" name="abe_save_target" value="exit" class="button"><?php esc_html_e( 'Save and Exit', 'abilities-editor' ); ?></button>
					<?php if ( is_array( $override ) ) : ?>
						<?php $delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'abilities-editor', 'abe_action' => 'delete', 'slug' => $slug ), admin_url( 'tools.php' ) ), 'abe_delete_meta_' . $slug, 'abe_delete_nonce' ); ?>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="button" onclick="return window.confirm(<?php echo esc_attr( wp_json_encode( __( 'Reset this override?', 'abilities-editor' ) ) ); ?>);"><?php esc_html_e( 'Reset Override', 'abilities-editor' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</form>
		<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var mcpPublic = document.getElementById( 'abe-mcp-public' );
			var mcpTypeRow = document.getElementById( 'abe-mcp-type-row' );

			if ( ! mcpPublic || ! mcpTypeRow ) {
				return;
			}

			function syncMcpTypeVisibility() {
				mcpTypeRow.style.display = mcpPublic.checked ? '' : 'none';
			}

			mcpPublic.addEventListener( 'change', syncMcpTypeVisibility );
			syncMcpTypeVisibility();
		} );
		</script>
		<?php
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit overrides.', 'abilities-editor' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		check_admin_referer( 'abe_save_meta_' . $slug, 'abe_meta_nonce' );

		$ability           = function_exists( 'wp_get_ability' ) ? wp_get_ability( $slug ) : null;
		$existing_override = Repository::get_by_slug( $slug );
		$existing_row      = is_array( $existing_override );
		$save_target       = self::save_target();
		$submitted         = self::submitted_values();
		$override          = self::build_override_values( $submitted, $ability );
		$override          = self::prepare_override_for_save( $override, $existing_override );

		if ( array() === $override ) {
			if ( $existing_row ) {
				Repository::delete( $slug );
				self::redirect_after_save( $slug, 'deleted', $save_target );
			}

			self::redirect_after_save( $slug, 'noop', $save_target );
		}

		$result = Repository::upsert( $slug, $override );
		self::redirect_after_save( $slug, $result ? 'saved' : 'error', $save_target );
	}


	public static function delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reset overrides.', 'abilities-editor' ) );
		}
		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'abe_delete_meta_' . $slug, 'abe_delete_nonce' );
		$deleted = Repository::delete( $slug );
		wp_safe_redirect( add_query_arg( array( 'page' => 'abilities-editor', 'abe_notice' => $deleted ? 'deleted' : 'error' ), admin_url( 'tools.php' ) ) );
		exit;
	}


	/**
	 * Returns the save button target selected by the user.
	 *
	 * @return string Either `stay` or `exit`.
	 */
	private static function save_target(): string {
		$target = isset( $_POST['abe_save_target'] ) ? sanitize_key( wp_unslash( $_POST['abe_save_target'] ) ) : 'stay';

		return 'exit' === $target ? 'exit' : 'stay';
	}

	/**
	 * Redirects after a save attempt based on the selected save target.
	 *
	 * @param string $slug        Ability slug being edited.
	 * @param string $notice      Notice slug to display after redirect.
	 * @param string $save_target Selected save behavior.
	 * @return void
	 */
	private static function redirect_after_save( string $slug, string $notice, string $save_target ): void {
		$args = array(
			'page'       => 'abilities-editor',
			'abe_notice' => $notice,
		);

		if ( 'exit' !== $save_target ) {
			$args['action'] = 'edit';
			$args['slug']   = $slug;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	private static function select( string $name, ?bool $value, bool $disabled ): void {
		printf(
			'<select name="%1$s" %2$s><option value="null" %3$s>null</option><option value="1" %4$s>true</option><option value="0" %5$s>false</option></select>',
			esc_attr( $name ),
			disabled( $disabled, true, false ),
			self::selected_option( null, $value ),
			self::selected_option( true, $value ),
			self::selected_option( false, $value )
		);
	}


	private static function mcp_type_select( string $value, bool $disabled ): void {
		$options = array(
			'tools'     => __( 'Tools', 'abilities-editor' ),
			'resources' => __( 'Resources', 'abilities-editor' ),
			'prompts'   => __( 'Prompts', 'abilities-editor' ),
		);

		$value = self::sanitize_mcp_type( $value );

		echo '<select name="mcp_type" ' . disabled( $disabled, true, false ) . '>';
		foreach ( $options as $option_value => $label ) {
			echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}


	private static function submitted_values(): array {
		return array(
			'readonly'     => self::normalize_nullable_bool( isset( $_POST['readonly'] ) ? wp_unslash( $_POST['readonly'] ) : null ),
			'destructive'  => self::normalize_nullable_bool( isset( $_POST['destructive'] ) ? wp_unslash( $_POST['destructive'] ) : null ),
			'idempotent'   => self::normalize_nullable_bool( isset( $_POST['idempotent'] ) ? wp_unslash( $_POST['idempotent'] ) : null ),
			'show_in_rest' => isset( $_POST['show_in_rest'] ),
			'mcp_public'   => isset( $_POST['mcp_public'] ),
			'mcp_type'     => self::sanitize_mcp_type( isset( $_POST['mcp_type'] ) ? wp_unslash( $_POST['mcp_type'] ) : '' ),
		);
	}


	private static function resolved_values( ?array $override, $ability ): array {
		$meta        = $ability instanceof \WP_Ability ? $ability->get_meta() : array();
		$annotations = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();

		return array(
			'readonly'     => self::coalesce( $override['readonly'] ?? null, $annotations['readonly'] ?? null ),
			'destructive'  => self::coalesce( $override['destructive'] ?? null, $annotations['destructive'] ?? null ),
			'idempotent'   => self::coalesce( $override['idempotent'] ?? null, $annotations['idempotent'] ?? null ),
			'show_in_rest' => self::coalesce( $override['show_in_rest'] ?? null, $meta['show_in_rest'] ?? false ),
			'mcp_public'   => self::coalesce( $override['mcp_public'] ?? null, $meta['mcp']['public'] ?? false ),
			'mcp_type'     => self::sanitize_mcp_type( self::coalesce( $override['mcp_type'] ?? '', $meta['mcp']['type'] ?? 'tools' ) ),
		);
	}

	private static function build_override_values( array $submitted, $ability ): array {
		if ( ! $ability instanceof \WP_Ability ) {
			return $submitted;
		}

		$defaults    = self::default_values( $ability );
		$overrides   = array();
		$fields      = array( 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public', 'mcp_type' );

		foreach ( $fields as $field ) {
			if ( $submitted[ $field ] !== $defaults[ $field ] ) {
				$overrides[ $field ] = $submitted[ $field ];
			}
		}

		return $overrides;
	}


	/**
	 * Ensures previously stored override keys are cleared when a field returns to
	 * its live default but the row still needs to exist for other overrides.
	 *
	 * Without this step, partial updates would preserve stale stored values for
	 * fields omitted from the current diff payload.
	 *
	 * @param array<string, mixed>      $override          Current diff-only override payload.
	 * @param array<string, mixed>|null $existing_override Previously stored override row, if any.
	 * @return array<string, mixed> Override payload safe to persist.
	 */
	private static function prepare_override_for_save( array $override, ?array $existing_override ): array {
		if ( ! is_array( $existing_override ) || array() === $override ) {
			return $override;
		}

		foreach ( array( 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public', 'mcp_type' ) as $field ) {
			if ( array_key_exists( $field, $override ) ) {
				continue;
			}

			if ( self::has_stored_override_value( $existing_override, $field ) ) {
				$override[ $field ] = null;
			}
		}

		return $override;
	}

	/**
	 * Checks whether the stored row currently contains an explicit override value
	 * for the provided field.
	 *
	 * @param array<string, mixed> $override Stored override row.
	 * @param string               $field    Override field name.
	 * @return bool True when the field currently stores an override.
	 */
	private static function has_stored_override_value( array $override, string $field ): bool {
		if ( ! array_key_exists( $field, $override ) ) {
			return false;
		}

		if ( 'mcp_type' === $field ) {
			return '' !== (string) $override[ $field ];
		}

		return null !== $override[ $field ];
	}

	private static function default_values( \WP_Ability $ability ): array {
		$meta        = $ability->get_meta();
		$annotations = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();

		return array(
			'readonly'     => self::normalize_nullable_bool( $annotations['readonly'] ?? null ),
			'destructive'  => self::normalize_nullable_bool( $annotations['destructive'] ?? null ),
			'idempotent'   => self::normalize_nullable_bool( $annotations['idempotent'] ?? null ),
			'show_in_rest' => (bool) ( $meta['show_in_rest'] ?? false ),
			'mcp_public'   => (bool) ( $meta['mcp']['public'] ?? false ),
			'mcp_type'     => self::default_mcp_type( $meta ),
		);
	}


	/**
	 * Returns the real stored/live default MCP type for diff comparisons.
	 *
	 * The edit form may display `tools` as the UI default, but when the live
	 * ability metadata has no `mcp.type` at all we must treat that as an empty
	 * default so selecting `tools` becomes a real override and gets persisted.
	 *
	 * @param array<string, mixed> $meta Ability metadata.
	 * @return string Default MCP type used for save diffing.
	 */
	private static function default_mcp_type( array $meta ): string {
		if ( ! isset( $meta['mcp'] ) || ! is_array( $meta['mcp'] ) || ! array_key_exists( 'type', $meta['mcp'] ) ) {
			return '';
		}

		return self::sanitize_mcp_type( $meta['mcp']['type'] );
	}

	private static function sanitize_mcp_type( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'tools', 'resources', 'prompts' ), true ) ? $value : 'tools';
	}


	private static function normalize_nullable_bool( $value ): ?bool {
		if ( null === $value || '' === $value || 'null' === $value ) {
			return null;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (bool) (int) $value;
		}

		$value = strtolower( trim( (string) $value ) );

		if ( in_array( $value, array( 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $value, array( 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return null;
	}


	private static function selected_option( ?bool $expected, ?bool $actual ): string {
		return $expected === $actual ? ' selected="selected"' : '';
	}

	private static function render_category_value( string $category, string $slug ): string {
		if ( '' === $category ) {
			return '&mdash;';
		}

		$value = esc_html( $category );

		if ( '' !== $slug && strtolower( $category ) !== strtolower( $slug ) ) {
			$value .= '<br /><small>' . esc_html( $slug ) . '</small>';
		}

		return $value;
	}

	private static function ability_category_label( string $slug ): string {
		if ( '' === $slug || ! function_exists( 'wp_get_ability_categories' ) ) {
			return '';
		}

		$categories = wp_get_ability_categories();
		$category   = $categories[ $slug ] ?? null;

		if ( $category instanceof \WP_Ability_Category ) {
			return $category->get_label();
		}

		return $slug;
	}

	private static function coalesce( $override, $fallback ) {
		return ( null === $override || '' === $override ) ? $fallback : $override;
	}

	private static function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) {
			return 'core';
		}
		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template = sanitize_key( (string) get_template() );
		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) {
			return 'theme:' . $namespace;
		}
		return '' !== $namespace ? $namespace : 'unknown';
	}
}
