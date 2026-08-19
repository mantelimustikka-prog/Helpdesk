<?php

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WPHelpdesk\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$path     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'HD_VERSION' ) || define( 'HD_VERSION', '1.0.0' );
defined( 'HD_PATH' ) || define( 'HD_PATH', dirname( __DIR__ ) . '/' );
defined( 'HD_URL' ) || define( 'HD_URL', 'https://example.test/wp-content/plugins/helpdesk/' );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'DB_NAME' ) || define( 'DB_NAME', 'wp_test' );
defined( 'HD_BASENAME' ) || define( 'HD_BASENAME', 'helpdesk/helpdesk.php' );
defined( 'EP_ROOT' ) || define( 'EP_ROOT', 64 );
defined( 'EP_PAGES' ) || define( 'EP_PAGES', 4096 );
defined( 'WP_HELPDESK_TESTING' ) || define( 'WP_HELPDESK_TESTING', true );

if ( ! function_exists( 'wp_helpdesk_test_reset_state' ) ) {
	function wp_helpdesk_test_reset_state(): void {
		$GLOBALS['wp_site_options'] = array();
		$GLOBALS['wp_site_option_updates'] = array();
		$GLOBALS['wp_options'] = array();
		$GLOBALS['wp_option_updates'] = array();
		$GLOBALS['wp_settings_errors'] = array();
		$GLOBALS['wp_current_user_caps'] = array(
			'hd_manage_settings' => true,
			'hd_manage_topics'   => true,
			'hd_manage_tickets'  => true,
			'hd_reply_tickets'   => true,
		);
		$GLOBALS['wp_valid_nonces'] = array(
			'hd_settings_save' => 'valid-settings-nonce',
			'hd_topic_action'  => 'valid-topic-nonce',
			'wp_rest'          => 'valid-rest-nonce',
		);
		$GLOBALS['wp_mail_calls'] = array();
		$GLOBALS['wp_enqueued_scripts'] = array();
		$GLOBALS['wp_inline_scripts'] = array();
		$GLOBALS['wp_localized_scripts'] = array();
		$GLOBALS['wp_safe_redirect_to'] = null;
		$GLOBALS['wp_filters'] = array();
		$GLOBALS['wp_query_vars'] = array();
		$GLOBALS['wp_rewrite_endpoints'] = array();
		$GLOBALS['wp_logged_in'] = true;
		$GLOBALS['wp_current_user'] = (object) array(
			'ID'           => 7,
			'display_name' => 'Agent Smith',
			'first_name'   => 'Agent',
			'last_name'    => 'Smith',
			'user_login'   => 'asmith',
			'user_email'   => 'agent@example.test',
		);
		$GLOBALS['wp_user_meta'] = array();
		$GLOBALS['wp_remote_post_response'] = array( 'response' => array( 'code' => 200 ) );
		$GLOBALS['wp_posts_index'] = array();
		$GLOBALS['wc_page_permalinks'] = array(
			'myaccount' => 'https://example.test/my-account/',
		);
		// Simulate an initialised WP rewrite object so permalink calls are safe.
		$GLOBALS['wp_rewrite'] = new stdClass();
		$_GET = array();
		$_POST = array();
		$_SERVER = array(
			'PHP_SELF'        => $_SERVER['PHP_SELF'] ?? 'phpunit',
			'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? __FILE__,
			'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ),
		);
	}
}

wp_helpdesk_test_reset_state();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;
		/** @var array<string, mixed> */
		public array $data;

		/** @param array<string, mixed> $data */
		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request implements ArrayAccess {
		/** @var array<string, mixed> */
		private array $params = array();
		/** @var array<string, string> */
		private array $headers = array();

		/** @param mixed $value */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/** @return mixed */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_header( string $key, string $value ): void {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( string $key ): string {
			return $this->headers[ strtolower( $key ) ] ?? '';
		}

		public function offsetExists( $offset ): bool {
			return array_key_exists( (string) $offset, $this->params );
		}

		/** @return mixed */
		public function offsetGet( $offset ) {
			return $this->params[ (string) $offset ] ?? null;
		}

		/** @param mixed $value */
		public function offsetSet( $offset, $value ): void {
			$this->params[ (string) $offset ] = $value;
		}

		public function offsetUnset( $offset ): void {
			unset( $this->params[ (string) $offset ] );
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		public $data;
		public int $status;

		/** @param mixed $data */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, object> */
		public array $posts = array();

		/** @param array<string, mixed> $args */
		public function __construct( array $args = array() ) {
			$search = trim( (string) ( $args['s'] ?? '' ) );
			$posts = array_values( $GLOBALS['wp_posts_index'] ?? array() );
			if ( '' !== $search ) {
				$posts = array_values(
					array_filter(
						$posts,
						static function ( object $post ) use ( $search ): bool {
							$haystack = strtolower( (string) $post->post_title . ' ' . (string) $post->post_content );
							return false !== strpos( $haystack, strtolower( $search ) );
						}
					)
				);
			}
			$this->posts = array_slice( $posts, 0, (int) ( $args['posts_per_page'] ?? 5 ) );
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = '' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = '' ): void {
		echo htmlspecialchars( $text, ENT_QUOTES );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = '' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		$title = strtolower( trim( $title ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title ) ?: '';
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?: '' );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $value ): string {
		return filter_var( trim( $value ), FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $html ): string {
		return strip_tags( $html, '<a><b><br><code><div><em><i><p><span><strong><ul><ol><li>' );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( string $html, array $allowed_html ): string {
		return strip_tags( $html, '<code>' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( string $value ): string {
		return addslashes( $value );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $value ): string {
		return filter_var( trim( $value ), FILTER_VALIDATE_URL ) ? trim( $value ) : '';
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = $checked == $current ? 'checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, bool $display = true ): string {
		$result = $selected == $current ? 'selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Submit', string $type = 'primary', string $name = 'submit', bool $wrap = true, array $other_attributes = array() ): void {
		echo '<button type="submit">' . htmlspecialchars( $text, ENT_QUOTES ) . '</button>';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '', string $name = '_wpnonce' ): void {
		$value = $GLOBALS['wp_valid_nonces'][ $action ] ?? 'nonce';
		echo '<input type="hidden" name="' . htmlspecialchars( $name, ENT_QUOTES ) . '" value="' . htmlspecialchars( $value, ENT_QUOTES ) . '">';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action ): bool {
		return ( $GLOBALS['wp_valid_nonces'][ $action ] ?? '' ) === $nonce;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return ! empty( $GLOBALS['wp_current_user_caps'][ $capability ] );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( string $message ): void {
		throw new RuntimeException( $message );
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['wp_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( string $setting = '' ): void {
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( string $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wp_site_options'] ) ? $GLOBALS['wp_site_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	function update_site_option( string $key, $value ): bool {
		$GLOBALS['wp_site_options'][ $key ] = $value;
		$GLOBALS['wp_site_option_updates'][] = array( $key, $value );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		$GLOBALS['wp_options'][ $key ] = $value;
		$GLOBALS['wp_option_updates'][] = array( $key, $value );
		return true;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type ): string {
		return 'mysql' === $type ? '2026-08-18 21:14:13' : '0';
	}
}

if ( ! function_exists( 'get_current_network_id' ) ) {
	function get_current_network_id(): int {
		return 1;
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id(): int {
		return 1;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/' ) . '/';
	}
}

if ( ! function_exists( 'network_admin_url' ) ) {
	function network_admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/network/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( string $redirect = '' ): string {
		return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $redirect );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action ): string {
		return $GLOBALS['wp_valid_nonces'][ $action ] ?? ( 'nonce-' . $action );
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( string $to, string $subject, string $message, array $headers = array() ): bool {
		$GLOBALS['wp_mail_calls'][] = compact( 'to', 'subject', 'message', 'headers' );
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( string $text, int $num_words = 55 ): string {
		$words = preg_split( '/\s+/', trim( $text ) ) ?: array();
		if ( count( $words ) <= $num_words ) {
			return trim( $text );
		}
		return implode( ' ', array_slice( $words, 0, $num_words ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string $ver = '', bool $in_footer = false ): void {
		$GLOBALS['wp_enqueued_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( string $handle, string $data ): void {
		$GLOBALS['wp_inline_scripts'][ $handle ] = $data;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $data ): void {
		$GLOBALS['wp_localized_scripts'][ $handle ] = array(
			'object_name' => $object_name,
			'data'        => $data,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), string $ver = '' ): void {
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location ): void {
		$GLOBALS['wp_safe_redirect_to'] = $location;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		return (bool) ( $GLOBALS['wp_logged_in'] ?? false );
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user(): object {
		return $GLOBALS['wp_current_user'];
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return (int) $GLOBALS['wp_current_user']->ID;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( int $user_id, string $key, bool $single = true ) {
		return $GLOBALS['wp_user_meta'][ $user_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( int $user_id, string $key, $value ): bool {
		$GLOBALS['wp_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['wp_filters'][ $hook ][] = $callback;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['wp_filters'][ $hook ][] = $callback;
	}
}

if ( ! function_exists( 'add_rewrite_endpoint' ) ) {
	function add_rewrite_endpoint( string $name, int $places ): void {
		$GLOBALS['wp_rewrite_endpoints'][] = array(
			'name'   => $name,
			'places' => $places,
		);
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, ...$args ): void {
		foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( string $key, $default = '' ) {
		return $GLOBALS['wp_query_vars'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'wc_get_page_permalink' ) ) {
	function wc_get_page_permalink( string $page ): string {
		return $GLOBALS['wc_page_permalinks'][ $page ] ?? '';
	}
}

if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
	function wc_get_account_endpoint_url( string $endpoint ): string {
		return rtrim( wc_get_page_permalink( 'myaccount' ), '/' ) . '/' . trim( $endpoint, '/' ) . '/';
	}
}

if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
	function wc_get_endpoint_url( string $endpoint, string $value = '', string $permalink = '' ): string {
		$base = '' !== $permalink ? $permalink : wc_get_page_permalink( 'myaccount' );
		$url  = trailingslashit( $base ) . trim( $endpoint, '/' ) . '/';

		if ( '' !== trim( $value, '/' ) ) {
			$url .= trim( $value, '/' ) . '/';
		}

		return $url;
	}
}

if ( ! function_exists( 'get_users' ) ) {
	function get_users( array $args = array() ): array {
		return array( 1, 2 );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( object $post ): string {
		return 'https://example.test/?p=' . (int) $post->ID;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return $GLOBALS['wp_posts_index'][ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $namespace, string $route, array $args ): void {
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = array() ) {
		return $GLOBALS['wp_remote_post_response'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	function wp_check_filetype_and_ext( string $file, string $filename ): array {
		if ( ! empty( $GLOBALS['hd_test_filetype'] ) ) {
			return $GLOBALS['hd_test_filetype'];
		}
		$ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$map  = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'zip'  => 'application/zip',
		);
		$type = $map[ $ext ] ?? 'application/octet-stream';
		return array( 'type' => $type, 'ext' => $ext );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $name ): string {
		return preg_replace( '/[^a-zA-Z0-9._-]/', '-', $name );
	}
}

// Stub WooCommerce core class so class_exists('WooCommerce') returns true in
// tests that exercise the WC integration path.
if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {
	}
}
