<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader and minimal WP stubs.
 *
 * This bootstrap provides just enough WordPress function stubs to run
 * unit tests for pure-logic classes that do not require a full WP install.
 *
 * @package AcrossAI_Abilities_Manager
 */

// Define ABSPATH so files protected by `defined('ABSPATH') || exit` don't bail.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Minimal WordPress function / class stubs
// ---------------------------------------------------------------------------

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub: mirrors WP sanitize_text_field behaviour for unit tests.
	 *
	 * @param  string $str Input string.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( wp_check_invalid_utf8( strip_tags( $str ) ) );
	}
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stub: returns the string unchanged (valid UTF-8 assumption in tests).
	 *
	 * @param  string $string Input string.
	 * @return string
	 */
	function wp_check_invalid_utf8( string $string ): string {
		return $string;
	}
}

if ( ! function_exists( '__' ) ) {
	/** Stub: returns the string unchanged (i18n not needed in unit tests). */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/** Stub: simple HTML escaping. */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/** Stub: simple attribute escaping. */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/** Stub: pass-through for unit tests (no DB filtering needed). */
	function esc_url_raw( string $url, array $protocols = array() ): string {
		return $url;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/** Stub: delegates to json_encode. */
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** Stub: checks if value is a WP_Error instance. */
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/** Stub: no-op hook registration for unit tests. */
	function add_action( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/** Stub: no-op filter registration for unit tests. */
	function add_filter( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/** Stub: returns the first value unchanged (no filters registered). */
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/** Stub: no-op action dispatch. */
	function do_action( string $hook, mixed ...$args ): void {}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/** Stub: merges args with defaults similar to WP. */
	function wp_parse_args( mixed $args, mixed $defaults = array() ): array {
		if ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$r = $args;
		} else {
			parse_str( $args, $r );
		}
		return array_merge( (array) $defaults, $r );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/** Stub: returns $default (no DB in unit tests). */
	function get_option( string $option, mixed $default = false ): mixed {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/** Stub: no-op option update. */
	function update_option( string $option, mixed $value, string|bool $autoload = 'yes' ): bool {
		return false;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/** Stub: no-op option delete. */
	function delete_option( string $option ): bool {
		return false;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub: lowercases and strips to [a-z0-9_-] only.
	 *
	 * Mirrors the WordPress core behavior closely enough for unit tests of
	 * key-shape sanitizers (Feature 033 Library Registry sub_group helper).
	 *
	 * @param  string $key Raw key string.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'acrossai_test_site_options' ) ) {
	/**
	 * The shared site-option store for get_site_option / update_site_option
	 * stubs. Pass an associative array to merge writes; pass nothing to read
	 * the current store. Pass array() to reset.
	 *
	 * @param  array<string,mixed>|null $write Optional writes to merge; empty array clears.
	 * @return array<string,mixed>
	 */
	function acrossai_test_site_options( ?array $write = null ): array {
		static $store = array();
		if ( null !== $write ) {
			if ( array() === $write ) {
				$store = array();
			} else {
				$store = array_merge( $store, $write );
			}
		}
		return $store;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Stub: reads from the shared acrossai_test_site_options store.
	 *
	 * @param  string $option  Option name.
	 * @param  mixed  $default Default value if not set.
	 * @return mixed
	 */
	function get_site_option( string $option, mixed $default = false ): mixed {
		$store = acrossai_test_site_options();
		return array_key_exists( $option, $store ) ? $store[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	/**
	 * Stub: writes to the shared acrossai_test_site_options store.
	 *
	 * @param  string $option Option name.
	 * @param  mixed  $value  New value.
	 * @return bool
	 */
	function update_site_option( string $option, mixed $value ): bool {
		acrossai_test_site_options( array( $option => $value ) );
		return true;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	/** Stub: basic SQL escaping (no DB in unit tests). */
	function esc_sql( mixed $sql ): string|array {
		return is_array( $sql )
			? array_map( 'esc_sql', $sql )
			: addslashes( (string) $sql );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/** Stub: returns current timestamp. */
	function current_time( string $type, bool $gmt = false ): string|int {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	/** Stub: passes through unchanged (transliteration not needed in unit tests). */
	function remove_accents( string $string ): string {
		return $string;
	}
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
	/** Stub: no-op user context (no auth in unit tests). */
	function wp_set_current_user( int $id, string $name = '' ): mixed {
		return null;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/** Stub: always returns 0 (no logged-in user in unit tests). */
	function get_current_user_id(): int {
		return 0;
	}
}

if ( ! function_exists( 'user_can' ) ) {
	/** Stub: returns false (no capability checks in unit tests). */
	function user_can( mixed $user, string $capability, mixed ...$args ): bool {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/** Stub: returns false. */
	function current_user_can( string $capability, mixed ...$args ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	/** Stub: no external object cache in unit tests. */
	function wp_using_ext_object_cache(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	/** Stub: always misses. */
	function wp_cache_get( mixed $key, string $group = '', bool $force = false, mixed &$found = null ): mixed {
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/** Stub: no-op cache set. */
	function wp_cache_set( mixed $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/** Stub: no-op cache delete. */
	function wp_cache_delete( mixed $key, string $group = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/** Stub: returns false (no session in unit tests). */
	function is_user_logged_in(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_kses_data' ) ) {
	/** Stub: strips HTML entities — minimal safe pass-through for unit tests. */
	function wp_kses_data( string $data ): string {
		return strip_tags( $data );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/** Stub: strips all HTML. */
	function wp_kses( string $string, array $allowed_html, array $allowed_protocols = array() ): string {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/** Stub: strips all HTML except basic post HTML. */
	function wp_kses_post( string $data ): string {
		return strip_tags( $data, '<p><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><br><hr>' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/** Stub: converts to absolute integer. */
	function absint( mixed $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/** Stub: delegates to PHP number_format. */
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/** Stub: ensures trailing slash. */
	function trailingslashit( string $string ): string {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/** Stub: removes trailing slash. */
	function untrailingslashit( string $string ): string {
		return rtrim( $string, '/\\' );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub sufficient for unit tests.
	 */
	class WP_Error {
		/** @var array<string,array<mixed>> */
		private array $errors = array();
		/** @var array<string,mixed> */
		private array $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional data.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][]      = $message;
				$this->error_data[ $code ]    = $data;
			}
		}

		/** @return array<string,array<mixed>> */
		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		/** @param string $code */
		public function get_error_messages( string $code = '' ): array {
			return $code ? ( $this->errors[ $code ] ?? array() ) : array_merge( ...array_values( $this->errors ) );
		}

		/** @param string $code */
		public function get_error_data( string $code = '' ): mixed {
			return $code ? ( $this->error_data[ $code ] ?? null ) : reset( $this->error_data );
		}

		/** Returns the first error code as a string (singular form). */
		public function get_error_code(): string {
			$codes = $this->get_error_codes();
			return $codes[0] ?? '';
		}

		/** @param string $message */
		public function add( string $code, string $message, mixed $data = '' ): void {
			$this->errors[ $code ][]   = $message;
			$this->error_data[ $code ] = $data;
		}
	}
}

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Alias: in unit-only mode WP_UnitTestCase is a plain PHPUnit TestCase.
	 */
	class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
		/**
		 * Assert that $value is a WP_Error instance.
		 *
		 * @param mixed  $value   Value to check.
		 * @param string $message Optional failure message.
		 */
		public static function assertWPError( $value, string $message = '' ): void {
			static::assertInstanceOf( WP_Error::class, $value, $message );
		}
	}
}
