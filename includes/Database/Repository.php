<?php
/**
 * Overrides repository.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Database;

defined( 'ABSPATH' ) || exit;

class Repository {
	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'provider' => '',
				'search' => '',
				'page' => 1,
				'per_page' => 20,
				'orderby' => 'ability_slug',
				'order' => 'ASC',
			)
		);
		$table = Schema::get_table_name();
		$where = array( '1 = %d' );
		$params = array( 1 );
		if ( '' !== $args['provider'] ) {
			$where[] = 'provider = %s';
			$params[] = sanitize_text_field( (string) $args['provider'] );
		}
		if ( '' !== $args['search'] ) {
			$where[] = 'ability_slug LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
		}
		$orderby = in_array( $args['orderby'], array( 'ability_slug', 'provider', 'updated_at', 'created_at' ), true ) ? $args['orderby'] : 'ability_slug';
		$order = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$page = max( 1, (int) $args['page'] );
		$per_page = max( 0, (int) $args['per_page'] );
		$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : ( $total > 0 ? 1 : 0 );
		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order}";
		$query_params = $params;
		if ( $per_page > 0 ) {
			$sql .= ' LIMIT %d OFFSET %d';
			$query_params[] = $per_page;
			$query_params[] = ( $page - 1 ) * $per_page;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'items' => array_map( array( __CLASS__, 'prepare_row' ), is_array( $rows ) ? $rows : array() ),
			'total' => $total,
			'pages' => $pages,
			'page' => $page,
			'per_page' => $per_page,
		);
	}

	public static function get_by_slug( string $slug ): ?array {
		$row = self::get_raw_by_slug( $slug );
		return is_array( $row ) ? self::prepare_row( $row ) : null;
	}

	public static function get_by_provider( string $provider ): array {
		$result = self::get_all( array( 'provider' => $provider, 'per_page' => 0 ) );
		return $result['items'];
	}

	public static function count_by_provider(): array {
		global $wpdb;
		$table = Schema::get_table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT provider, COUNT(*) AS count FROM {$table} WHERE 1 = %d GROUP BY provider", 1 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['provider'] ] = (int) $row['count'];
		}
		return $counts;
	}

	public static function upsert( string $slug, array $data ): ?array {
		global $wpdb;
		$slug = sanitize_text_field( $slug );
		$existing = self::get_raw_by_slug( $slug );
		$record = self::build_record( $slug, $data, $existing );
		if ( is_array( $existing ) ) {
			$result = $wpdb->update( Schema::get_table_name(), $record, array( 'ability_slug' => $slug ), self::formats( $record ), array( '%s' ) );
		} else {
			$result = $wpdb->insert( Schema::get_table_name(), $record, self::formats( $record ) );
		}
		if ( false === $result ) {
			return null;
		}
		return self::get_by_slug( $slug );
	}


	public static function delete( string $slug ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( Schema::get_table_name(), array( 'ability_slug' => sanitize_text_field( $slug ) ), array( '%s' ) );
		return false !== $deleted;
	}

	public static function exists( string $slug ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::get_table_name() . ' WHERE ability_slug = %s', sanitize_text_field( $slug ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $count > 0;
	}

	private static function get_raw_by_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::get_table_name() . ' WHERE ability_slug = %s', sanitize_text_field( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : null;
	}

	private static function prepare_row( array $row ): array {
		return array(
			'id' => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'ability_slug' => (string) ( $row['ability_slug'] ?? '' ),
			'provider' => (string) ( $row['provider'] ?? '' ),
			'readonly' => self::to_bool( $row['readonly'] ?? null ),
			'destructive' => self::to_bool( $row['destructive'] ?? null ),
			'idempotent' => self::to_bool( $row['idempotent'] ?? null ),
			'show_in_rest' => self::to_bool( $row['show_in_rest'] ?? null ),
			'mcp_public' => self::to_bool( $row['mcp_public'] ?? null ),
			'mcp_type' => (string) ( $row['mcp_type'] ?? '' ),
			'custom_meta' => self::decode_meta( $row['custom_meta'] ?? null ),
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'updated_at' => (string) ( $row['updated_at'] ?? '' ),
		);
	}


	private static function build_record( string $slug, array $data, ?array $existing ): array {
		$provider    = isset( $data['provider'] ) ? sanitize_text_field( (string) $data['provider'] ) : (string) ( $existing['provider'] ?? self::detect_provider( $slug ) );
		$custom_meta = array_key_exists( 'custom_meta', $data ) ? self::encode_meta( $data['custom_meta'] ) : ( $existing['custom_meta'] ?? null );
		$timestamp   = current_time( 'mysql' );
		$record      = array(
			'ability_slug' => $slug,
			'provider' => $provider,
			'readonly' => self::from_bool( self::incoming_value( $data, $existing, 'readonly' ) ),
			'destructive' => self::from_bool( self::incoming_value( $data, $existing, 'destructive' ) ),
			'idempotent' => self::from_bool( self::incoming_value( $data, $existing, 'idempotent' ) ),
			'show_in_rest' => self::from_bool( self::incoming_value( $data, $existing, 'show_in_rest' ) ),
			'mcp_public' => self::from_bool( self::incoming_value( $data, $existing, 'mcp_public' ) ),
			'mcp_type' => self::nullable_string( self::incoming_value( $data, $existing, 'mcp_type' ) ),
			'custom_meta' => $custom_meta,
			'updated_at' => $timestamp,
		);

		if ( ! is_array( $existing ) ) {
			$record['created_at'] = $timestamp;
		}

		return $record;
	}


	/**
	 * Resolves an incoming value for persistence while preserving explicit nulls.
	 *
	 * The repository uses this helper instead of the null coalescing operator so
	 * callers can intentionally clear a stored override by passing `null`.
	 *
	 * @param array<string, mixed>      $data     Incoming values for the save operation.
	 * @param array<string, mixed>|null $existing Existing row, if one is already stored.
	 * @param string                    $field    Field name to resolve.
	 * @return mixed Resolved value from input or existing storage.
	 */
	private static function incoming_value( array $data, ?array $existing, string $field ) {
		if ( array_key_exists( $field, $data ) ) {
			return $data[ $field ];
		}

		return $existing[ $field ] ?? null;
	}

	private static function formats( array $record ): array {
		return array_map( static function ( $value ): string { return is_int( $value ) ? '%d' : '%s'; }, $record );
	}

	private static function from_bool( $value ): ?int {
		$value = self::normalize_bool( $value );
		return null === $value ? null : ( $value ? 1 : 0 );
	}

	private static function to_bool( $value ): ?bool {
		return null === $value || '' === $value ? null : (bool) (int) $value;
	}

	private static function normalize_bool( $value ): ?bool {
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


	private static function nullable_string( $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$value = sanitize_text_field( (string) $value );
		return '' === $value ? null : $value;
	}

	private static function encode_meta( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return wp_json_encode( $decoded );
			}
		}
		return wp_json_encode( $value );
	}

	private static function decode_meta( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$decoded = json_decode( (string) $value, true );
		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
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
