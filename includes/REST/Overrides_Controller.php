<?php
/**
 * REST controller for overrides.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\REST;

use Abilities_Editor\Database\Repository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class Overrides_Controller extends WP_REST_Controller {
	public static function register_routes(): void {
		$controller = new self();
		$controller->namespace = 'abilities-editor/v1';
		$controller->rest_base = 'overrides';
		$controller->routes();
	}

	private function routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array( array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_overrides' ), 'permission_callback' => array( $this, 'check_permission' ) ) ) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-z0-9-]+\/[a-z0-9-]+)', array( array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_override' ), 'permission_callback' => array( $this, 'check_permission' ) ), array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'upsert_override' ), 'permission_callback' => array( $this, 'check_permission' ) ), array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_override' ), 'permission_callback' => array( $this, 'check_permission' ) ) ) );
	}

	public function get_overrides( WP_REST_Request $request ): WP_REST_Response {
		$provider = sanitize_text_field( (string) $request->get_param( 'provider' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$page = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$result = Repository::get_all( array( 'provider' => $provider, 'per_page' => 0 ) );
		$items = $this->filter_items( $result['items'], $search );
		$total = count( $items );
		$pages = (int) ceil( $total / $per_page );
		$items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
		$response = rest_ensure_response( array( 'abilities_count' => count( function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array() ), 'stats' => $this->stats(), 'overrides' => array_map( array( $this, 'prepare_response' ), $items ) ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}


	public function get_override( WP_REST_Request $request ) {
		$override = Repository::get_by_slug( sanitize_text_field( (string) $request['slug'] ) );
		if ( ! $override ) {
			return new WP_Error( 'abilities_editor_override_not_found', __( 'Override not found.', 'abilities-editor' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->prepare_response( $override ) );
	}

	public function upsert_override( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$data = array();
		foreach ( array( 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public', 'mcp_type', 'custom_meta' ) as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$data[ $key ] = $params[ $key ];
			}
		}
		$override = Repository::upsert( sanitize_text_field( (string) $request['slug'] ), $data );
		if ( ! $override ) {
			return new WP_Error( 'abilities_editor_override_save_failed', __( 'Override could not be saved.', 'abilities-editor' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( $this->prepare_response( $override ) );
	}

	public function delete_override( WP_REST_Request $request ) {
		$slug = sanitize_text_field( (string) $request['slug'] );
		if ( ! Repository::exists( $slug ) ) {
			return new WP_Error( 'abilities_editor_override_not_found', __( 'Override not found.', 'abilities-editor' ), array( 'status' => 404 ) );
		}
		if ( ! Repository::delete( $slug ) ) {
			return new WP_Error( 'abilities_editor_override_delete_failed', __( 'Override could not be deleted.', 'abilities-editor' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Deleted', 'abilities-editor' ) ) );
	}


	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function prepare_response( array $override ): array {
		$override['edit_url'] = add_query_arg( array( 'page' => 'abilities-editor', 'action' => 'edit', 'slug' => $override['ability_slug'] ), admin_url( 'tools.php' ) );
		return $override;
	}

	private function filter_items( array $items, string $search ): array {
		if ( '' === $search ) {
			return $items;
		}
		$abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		return array_values( array_filter( $items, static function ( array $item ) use ( $search, $abilities ): bool { $label = isset( $abilities[ $item['ability_slug'] ] ) ? $abilities[ $item['ability_slug'] ]->get_label() : ''; return false !== stripos( $item['ability_slug'], $search ) || false !== stripos( $label, $search ); } ) );
	}

	private function stats(): array {
		$stats = array( 'core' => 0, 'plugins' => 0, 'theme' => 0 );
		foreach ( function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array() as $ability ) {
			$provider = $this->detect_provider( $ability->get_name() );
			if ( 'core' === $provider ) { ++$stats['core']; }
			elseif ( 0 === strpos( $provider, 'theme:' ) ) { ++$stats['theme']; }
			else { ++$stats['plugins']; }
		}
		return $stats;
	}

	private function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) { return 'core'; }
		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template = sanitize_key( (string) get_template() );
		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) { return 'theme:' . $namespace; }
		return '' !== $namespace ? $namespace : 'unknown';
	}
}
