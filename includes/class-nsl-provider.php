<?php
/**
 * Base class of a login provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login provider.
 *
 * A provider returns a normalised profile array from handle_callback():
 * id, email, email_verified, login, display_name, first_name, last_name, avatar, profile_url.
 */
abstract class NookSL_Provider {

	/**
	 * Provider id, e.g. `google`.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human readable name.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Brand background colour of the button.
	 *
	 * @return string
	 */
	abstract public function color();

	/**
	 * Settings fields: key => [ label, type, description ].
	 *
	 * @return array
	 */
	abstract public function fields();

	/**
	 * Developer console where the application is registered.
	 *
	 * @return string
	 */
	abstract public function console_url();

	/**
	 * Short setup instructions shown in the admin.
	 *
	 * @return string
	 */
	abstract public function instructions();

	/**
	 * URL the user is sent to.
	 *
	 * @param array $flow Flow data: state, verifier.
	 * @return string|WP_Error
	 */
	abstract public function authorize_url( array $flow );

	/**
	 * Handles the provider redirect back to the site.
	 *
	 * @param array $query Request query (unslashed).
	 * @param array $flow  Flow data stored at start.
	 * @return array|WP_Error Normalised profile.
	 */
	abstract public function handle_callback( array $query, array $flow );

	/**
	 * Reads the flow state from the callback request.
	 *
	 * @param array $query Request query.
	 * @return string
	 */
	public function callback_state( array $query ) {
		return isset( $query['state'] ) ? (string) $query['state'] : '';
	}

	/**
	 * Provider settings value.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public function option( $key ) {
		$opts = NookSL_Settings::provider( $this->id() );
		return isset( $opts[ $key ] ) ? trim( (string) $opts[ $key ] ) : '';
	}

	/**
	 * Fields that must be filled in before the provider can be used.
	 *
	 * @return string[]
	 */
	public function required_fields() {
		return array( 'client_id', 'client_secret' );
	}

	/**
	 * Enabled in settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === $this->option( 'enabled' );
	}

	/**
	 * All required fields are filled.
	 *
	 * @return bool
	 */
	public function is_configured() {
		foreach ( $this->required_fields() as $key ) {
			if ( '' === $this->option( $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Usable on the site.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_enabled() && $this->is_configured();
	}

	/**
	 * Redirect (callback) URL registered in the provider console.
	 *
	 * @return string
	 */
	public function callback_url() {
		return add_query_arg( 'nooksl_callback', $this->id(), wp_login_url() );
	}

	/**
	 * Builds an error object.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	protected function error( $message ) {
		return new WP_Error( 'nooksl_provider', $message );
	}

	/**
	 * Performs an HTTP request and decodes JSON.
	 *
	 * @param string $method GET or POST.
	 * @param string $url    URL.
	 * @param array  $args   wp_remote_request() arguments.
	 * @return array|WP_Error
	 */
	protected function request_json( $method, $url, array $args = array() ) {
		$args = array_merge(
			array(
				'method'  => $method,
				'timeout' => 20,
			),
			$args
		);
		if ( empty( $args['headers']['Accept'] ) ) {
			$args['headers']['Accept'] = 'application/json';
		}
		$args     = apply_filters( 'nooksl_http_args', $args, $url, $this->id() );
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $this->error( $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$detail = '';
			if ( is_array( $body ) ) {
				foreach ( array( 'error_description', 'message', 'error' ) as $k ) {
					if ( ! empty( $body[ $k ] ) && is_string( $body[ $k ] ) ) {
						$detail = $body[ $k ];
						break;
					}
				}
			}
			/* translators: 1: provider name, 2: HTTP status code, 3: error details. */
			return $this->error( trim( sprintf( __( '%1$s returned an error (HTTP %2$d). %3$s', 'nook-social-login' ), $this->label(), $code, $detail ) ) );
		}
		return $body;
	}

	/**
	 * Profile with all keys present.
	 *
	 * @param array $data Known profile values.
	 * @return array
	 */
	protected function profile( array $data ) {
		return array_merge(
			array(
				'id'             => '',
				'email'          => '',
				'email_verified' => false,
				'login'          => '',
				'display_name'   => '',
				'first_name'     => '',
				'last_name'      => '',
				'avatar'         => '',
				'profile_url'    => '',
			),
			$data
		);
	}
}
