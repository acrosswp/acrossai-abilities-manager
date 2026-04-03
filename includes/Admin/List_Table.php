<?php
/**
 * Abilities list table.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Admin;

use Abilities_Editor\Database\Repository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class List_Table extends \WP_List_Table {
	private array $abilities = array();
	private array $overrides = array();
	private array $categories = array();
	private array $provider_counts = array(
		'core'   => 0,
		'plugin' => 0,
		'theme'  => 0,
	);

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ability-override',
				'plural'   => 'ability-overrides',
				'ajax'     => false,
				'screen'   => 'tools_page_abilities-editor',
			)
		);
	}

	public function get_columns(): array {
		return array(
			'name'         => __( 'Name', 'abilities-editor' ),
			'slug'         => __( 'Slug', 'abilities-editor' ),
			'provider'     => __( 'Provider', 'abilities-editor' ),
			'category'     => __( 'Category', 'abilities-editor' ),
			'readonly'     => __( 'Readonly', 'abilities-editor' ),
			'destructive'  => __( 'Destructive', 'abilities-editor' ),
			'idempotent'   => __( 'Idempotent', 'abilities-editor' ),
			'show_in_rest' => __( 'Show in REST', 'abilities-editor' ),
			'mcp_public'   => __( 'MCP Public', 'abilities-editor' ),
			'actions'      => __( 'Actions', 'abilities-editor' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'name'         => array( 'name', false ),
			'slug'         => array( 'slug', false ),
			'provider'     => array( 'provider', false ),
			'category'     => array( 'category', false ),
			'readonly'     => array( 'readonly', false ),
			'destructive'  => array( 'destructive', false ),
			'idempotent'   => array( 'idempotent', false ),
			'show_in_rest' => array( 'show_in_rest', false ),
			'mcp_public'   => array( 'mcp_public', false ),
		);
	}

	public function prepare_items(): void {
		$this->abilities  = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		$this->categories = function_exists( 'wp_get_ability_categories' ) ? wp_get_ability_categories() : array();
		$result           = Repository::get_all( array( 'per_page' => 0 ) );
		$this->overrides = array();

		foreach ( $result['items'] as $override ) {
			$this->overrides[ $override['ability_slug'] ] = $override;
		}

		$items  = $this->filter_items( $this->build_items() );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$hidden = $screen ? get_hidden_columns( $screen ) : array( 'destructive', 'idempotent' );

		usort( $items, array( $this, 'sort_items' ) );
		$this->_column_headers = array( $this->get_columns(), $hidden, $this->get_sortable_columns() );

		$per_page = $this->get_items_per_page( 'abilities_editor_per_page', 20 );
		$page     = $this->get_pagenum();
		$total    = count( $items );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
	}

	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '&mdash;';
	}

	public function column_name( $item ): string {
		$url  = add_query_arg( array( 'page' => 'abilities-editor', 'action' => 'view', 'slug' => $item['slug'] ), admin_url( 'tools.php' ) );
		$text = '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a></strong>';

		if ( ! empty( $item['description'] ) ) {
			$text .= '<p class="description">' . esc_html( $item['description'] ) . '</p>';
		}

		if ( ! empty( $item['has_override'] ) ) {
			$text .= '<p><span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . esc_html__( 'Override saved', 'abilities-editor' ) . '</p>';
		}

		return $text;
	}

	public function column_slug( $item ): string {
		return '<code>' . esc_html( $item['slug'] ) . '</code>';
	}

	public function column_provider( $item ): string {
		return esc_html( $this->provider_label( $item['provider'] ) );
	}

	public function column_category( $item ): string {
		if ( '' === $item['category'] ) {
			return '&mdash;';
		}

		$value = esc_html( $item['category'] );

		if ( '' !== $item['category_slug'] && strtolower( $item['category'] ) !== strtolower( $item['category_slug'] ) ) {
			$value .= '<br /><small>' . esc_html( $item['category_slug'] ) . '</small>';
		}

		return $value;
	}

	public function column_readonly( $item ): string {
		return $this->render_bool_value( $item['readonly'] );
	}

	public function column_destructive( $item ): string {
		return $this->render_bool_value( $item['destructive'] );
	}

	public function column_idempotent( $item ): string {
		return $this->render_bool_value( $item['idempotent'] );
	}

	public function column_show_in_rest( $item ): string {
		return $this->render_bool_value( $item['show_in_rest'] );
	}

	public function column_mcp_public( $item ): string {
		$value = $this->render_bool_value( $item['mcp_public'] );

		if ( true === $item['mcp_public'] && '' !== $item['mcp_type'] ) {
			$value .= '<br /><small>' . esc_html( $item['mcp_type'] ) . '</small>';
		}

		return $value;
	}

	public function column_actions( $item ): string {
		$view    = add_query_arg( array( 'page' => 'abilities-editor', 'action' => 'view', 'slug' => $item['slug'] ), admin_url( 'tools.php' ) );
		$edit    = add_query_arg( array( 'page' => 'abilities-editor', 'action' => 'edit', 'slug' => $item['slug'] ), admin_url( 'tools.php' ) );
		$actions = array(
			'<a class="button button-small" href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'abilities-editor' ) . '</a>',
			'<a class="button button-small button-primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'abilities-editor' ) . '</a>',
		);

		if ( ! empty( $item['has_override'] ) ) {
			$delete    = wp_nonce_url( add_query_arg( array( 'page' => 'abilities-editor', 'abe_action' => 'delete', 'slug' => $item['slug'] ), admin_url( 'tools.php' ) ), 'abe_delete_meta_' . $item['slug'], 'abe_delete_nonce' );
			$actions[] = '<a class="button button-small" href="' . esc_url( $delete ) . '" onclick="return window.confirm(' . esc_js( wp_json_encode( __( 'Reset this override?', 'abilities-editor' ) ) ) . ');">' . esc_html__( 'Reset', 'abilities-editor' ) . '</a>';
		}

		return implode( ' ', $actions );
	}

	public function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="abilities-editor-orderby"><?php esc_html_e( 'Sort by', 'abilities-editor' ); ?></label>
			<select name="orderby" id="abilities-editor-orderby">
				<option value="name" <?php selected( $orderby, 'name' ); ?>><?php esc_html_e( 'Name', 'abilities-editor' ); ?></option>
				<option value="slug" <?php selected( $orderby, 'slug' ); ?>><?php esc_html_e( 'Slug', 'abilities-editor' ); ?></option>
				<option value="provider" <?php selected( $orderby, 'provider' ); ?>><?php esc_html_e( 'Provider', 'abilities-editor' ); ?></option>
				<option value="category" <?php selected( $orderby, 'category' ); ?>><?php esc_html_e( 'Category', 'abilities-editor' ); ?></option>
				<option value="readonly" <?php selected( $orderby, 'readonly' ); ?>><?php esc_html_e( 'Readonly', 'abilities-editor' ); ?></option>
				<option value="destructive" <?php selected( $orderby, 'destructive' ); ?>><?php esc_html_e( 'Destructive', 'abilities-editor' ); ?></option>
				<option value="idempotent" <?php selected( $orderby, 'idempotent' ); ?>><?php esc_html_e( 'Idempotent', 'abilities-editor' ); ?></option>
				<option value="show_in_rest" <?php selected( $orderby, 'show_in_rest' ); ?>><?php esc_html_e( 'Show in REST', 'abilities-editor' ); ?></option>
				<option value="mcp_public" <?php selected( $orderby, 'mcp_public' ); ?>><?php esc_html_e( 'MCP Public', 'abilities-editor' ); ?></option>
			</select>
			<label class="screen-reader-text" for="abilities-editor-order"><?php esc_html_e( 'Order', 'abilities-editor' ); ?></label>
			<select name="order" id="abilities-editor-order">
				<option value="asc" <?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Ascending', 'abilities-editor' ); ?></option>
				<option value="desc" <?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Descending', 'abilities-editor' ); ?></option>
			</select>
			<?php submit_button( __( 'Sort', 'abilities-editor' ), 'secondary', 'abilities_editor_sort', false ); ?>
		</div>
		<?php
	}

	public function render_stats_bar(): void {
		$current = isset( $_REQUEST['provider'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs    = array(
			'all'    => array(
				'label' => __( 'All', 'abilities-editor' ),
				'count' => array_sum( $this->provider_counts ),
			),
			'core'   => array(
				'label' => __( 'Core', 'abilities-editor' ),
				'count' => $this->provider_counts['core'],
			),
			'plugin' => array(
				'label' => __( 'Plugins', 'abilities-editor' ),
				'count' => $this->provider_counts['plugin'],
			),
			'theme'  => array(
				'label' => __( 'Themes', 'abilities-editor' ),
				'count' => $this->provider_counts['theme'],
			),
		);

		echo '<ul class="subsubsub">';

		$index = 0;
		foreach ( $tabs as $provider => $tab ) {
			$label = esc_html( $tab['label'] ) . ' <span class="count">(' . (int) $tab['count'] . ')</span>';

			echo '<li class="' . esc_attr( $provider ) . '">';
			if ( $provider === $current ) {
				echo '<span class="current">' . wp_kses_post( $label ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $this->provider_tab_url( $provider ) ) . '">' . wp_kses_post( $label ) . '</a>';
			}
			echo '</li>';

			if ( $index < count( $tabs ) - 1 ) {
				echo ' | ';
			}

			++$index;
		}

		echo '</ul>';
	}

	private function build_items(): array {
		$items = array();

		foreach ( $this->abilities as $slug => $ability ) {
			$items[] = $this->build_item( (string) $slug, $ability, $this->overrides[ $slug ] ?? null );
		}

		foreach ( $this->overrides as $slug => $override ) {
			if ( isset( $this->abilities[ $slug ] ) ) {
				continue;
			}
			$items[] = $this->build_item( (string) $slug, null, $override );
		}

		return $items;
	}

	private function build_item( string $slug, $ability, ?array $override ): array {
		$meta          = $ability instanceof \WP_Ability ? $ability->get_meta() : array();
		$annotations   = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();
		$provider      = is_array( $override ) && ! empty( $override['provider'] ) ? (string) $override['provider'] : $this->detect_provider( $slug );
		$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
		$category      = $this->category_label( $category_slug );
		$kind          = $this->provider_kind( $provider );

		if ( isset( $this->provider_counts[ $kind ] ) ) {
			++$this->provider_counts[ $kind ];
		}

		return array(
			'name'         => $ability instanceof \WP_Ability ? (string) $ability->get_label() : $slug,
			'slug'         => $slug,
			'description'  => $ability instanceof \WP_Ability ? (string) $ability->get_description() : __( 'Registered override with no currently loaded ability.', 'abilities-editor' ),
			'provider'     => $provider,
			'category'     => $category,
			'category_slug'=> $category_slug,
			'provider_kind'=> $kind,
			'readonly'     => $this->coalesce_bool( $override['readonly'] ?? null, $annotations['readonly'] ?? null ),
			'destructive'  => $this->coalesce_bool( $override['destructive'] ?? null, $annotations['destructive'] ?? null ),
			'idempotent'   => $this->coalesce_bool( $override['idempotent'] ?? null, $annotations['idempotent'] ?? null ),
			'show_in_rest' => $this->coalesce_bool( $override['show_in_rest'] ?? null, $meta['show_in_rest'] ?? null ),
			'mcp_public'   => $this->coalesce_bool( $override['mcp_public'] ?? null, $meta['mcp']['public'] ?? null ),
			'mcp_type'     => $this->coalesce_text( $override['mcp_type'] ?? '', $meta['mcp']['type'] ?? '' ),
			'has_override' => is_array( $override ),
		);
	}

	private function filter_items( array $items ): array {
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provider = isset( $_REQUEST['provider'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' !== $search ) {
			$items = array_filter(
				$items,
				static function ( array $item ) use ( $search ): bool {
					return false !== stripos( $item['name'], $search )
						|| false !== stripos( $item['slug'], $search )
						|| false !== stripos( $item['description'], $search )
						|| false !== stripos( $item['category'], $search )
						|| false !== stripos( $item['category_slug'], $search );
				}
			);
		}

		if ( '' !== $provider && 'all' !== $provider ) {
			$items = array_filter(
				$items,
				static function ( array $item ) use ( $provider ): bool {
					return $provider === $item['provider_kind'];
				}
			);
		}

		return array_values( $items );
	}

	private function sort_items( array $left, array $right ): int {
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bool_columns = array( 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public' );

		if ( in_array( $orderby, $bool_columns, true ) ) {
			$value_a = $this->sort_bool_value( $left[ $orderby ] ?? null );
			$value_b = $this->sort_bool_value( $right[ $orderby ] ?? null );
		} else {
			$value_a = strtolower( (string) ( $left[ $orderby ] ?? '' ) );
			$value_b = strtolower( (string) ( $right[ $orderby ] ?? '' ) );
		}

		$result = $value_a <=> $value_b;

		return 'desc' === $order ? -$result : $result;
	}

	private function render_bool_value( ?bool $value ): string {
		if ( null === $value ) {
			return '&mdash;';
		}

		return $value ? esc_html__( 'Yes', 'abilities-editor' ) : esc_html__( 'No', 'abilities-editor' );
	}


	private function sort_bool_value( ?bool $value ): int {
		if ( null === $value ) {
			return -1;
		}

		return $value ? 1 : 0;
	}

	private function coalesce_bool( $override, $fallback ): ?bool {
		return null === $override ? ( is_bool( $fallback ) ? $fallback : null ) : (bool) $override;
	}

	private function coalesce_text( $override, $fallback ): string {
		$override = is_string( $override ) ? trim( $override ) : '';
		if ( '' !== $override ) {
			return $override;
		}

		return is_string( $fallback ) ? trim( $fallback ) : '';
	}

	private function category_label( string $slug ): string {
		if ( '' === $slug ) {
			return '';
		}

		$category = $this->categories[ $slug ] ?? null;

		if ( $category instanceof \WP_Ability_Category ) {
			return $category->get_label();
		}

		return $slug;
	}

	private function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) {
			return 'core';
		}

		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template   = sanitize_key( (string) get_template() );

		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) {
			return 'theme:' . $namespace;
		}

		return '' !== $namespace ? $namespace : 'unknown';
	}

	private function provider_kind( string $provider ): string {
		if ( 'core' === $provider ) {
			return 'core';
		}

		if ( 0 === strpos( $provider, 'theme:' ) ) {
			return 'theme';
		}

		return 'plugin';
	}

	private function provider_label( string $provider ): string {
		if ( 'core' === $provider ) {
			return __( 'Core', 'abilities-editor' );
		}

		if ( 0 === strpos( $provider, 'theme:' ) ) {
			return sprintf( __( 'Theme: %s', 'abilities-editor' ), substr( $provider, 6 ) );
		}

		if ( '' === $provider || 'unknown' === $provider ) {
			return __( 'Unknown', 'abilities-editor' );
		}

		return sprintf( __( 'Plugin: %s', 'abilities-editor' ), $provider );
	}

	private function provider_tab_url( string $provider ): string {
		$args = array(
			'page'     => 'abilities-editor',
			'provider' => $provider,
		);
		if ( isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['orderby'] = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( isset( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['order'] = sanitize_key( wp_unslash( $_REQUEST['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}
}
