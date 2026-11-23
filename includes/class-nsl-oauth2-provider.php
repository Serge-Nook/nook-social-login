<?php
/**
 * Generic OAuth 2.0 authorization code provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OAuth 2.0 provider with optional PKCE.
 */
abstract class NookSL_OAuth2_Provider extends NookSL_Provider {

	/**
	 * Authorization endpoint.
	 *
	 * @return string
	 */
	abstract protected function authorize_endpoint();

	/**
	 * Token endpoint.
	 *
	 * @return string
	 */
	abstract protected function token_endpoint();

	/**
	 * Fetches the user profile with an access token.
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 * @return array|WP_Error Normalised profile.
	 */
	abstract protected function fetch_profile( array $token, array $query );

	/**
	 * Space separated scopes.
	 *
	 * @return string
	 */
	protected function scope() {
		return '';
	}

	/**
	 * Use PKCE (S256).
	 *
	 * @return bool
	 */
	protected function use_pkce() {
		return false;
	}

	/**
	 * Send client credentials with HTTP Basic auth instead of the body.
	 *
	 * @return bool
	 */
	protected function basic_auth() {
		return false;
	}

	/**
	 * Extra authorization query parameters.
	 *
	 * @return array
	 */
	protected function extra_authorize_params() {
		return array();
	}

	/**
	 * Extra token request parameters.
	 *
	 * @param array $query Callback query.
	 * @param array $flow  Flow data.
	 * @return array
	 */
	protected function extra_token_params( array $query, array $flow ) {
		unset( $query, $flow );
		return array();
	}

	/**
	 * PKCE challenge for a verifier.
	 *
	 * @param string $verifier Code verifier.
	 * @return string
	 */
	protected function pkce_challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $flow Flow data.
	 */
	public function authorize_url( array $flow ) {
		$params = array(
			'response_type' => 'code',
			'client_id'     => $this->option( 'client_id' ),
			'redirect_uri'  => $this->callback_url(),
			'state'         => $flow['state'],
		);
		if ( '' !== $this->scope() ) {
			$params['scope'] = $this->scope();
		}
		if ( $this->use_pkce() ) {
			$params['code_challenge']        = $this->pkce_challenge( $flow['verifier'] );
			$params['code_challenge_method'] = 'S256';
		}
		$params = array_merge( $params, $this->extra_authorize_params() );
		$params = apply_filters( 'nooksl_authorize_params', $params, $this->id() );

		$endpoint = apply_filters( 'nooksl_endpoint', $this->authorize_endpoint(), 'authorize', $this->id() );
		return $endpoint . ( false === strpos( $endpoint, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $query Callback query.
	 * @param array $flow  Flow data.
	 */
	public function handle_callback( array $query, array $flow ) {
		if ( ! empty( $query['error'] ) ) {
			if ( in_array( $query['error'], array( 'access_denied', 'user_denied', 'cancel' ), true ) ) {
				return new WP_Error( 'nooksl_cancelled', __( 'Login was cancelled.', 'nook-social-login' ) );
			}
			$detail = ! empty( $query['error_description'] ) ? $query['error_description'] : $query['error'];
			return $this->error( sanitize_text_field( (string) $detail ) );
		}
		if ( empty( $query['code'] ) || ! is_string( $query['code'] ) ) {
			return $this->error( __( 'The authorization code is missing.', 'nook-social-login' ) );
		}

		$token = $this->exchange_code( $query['code'], $query, $flow );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$profile = $this->fetch_profile( $token, $query );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		if ( '' === (string) $profile['id'] ) {
			return $this->error( __( 'The provider did not return a user ID.', 'nook-social-login' ) );
		}
		return $profile;
	}

	/**
	 * Exchanges the authorization code for a token.
	 *
	 * @param string $code  Authorization code.
	 * @param array  $query Callback query.
	 * @param array  $flow  Flow data.
	 * @return array|WP_Error
	 */
	protected function exchange_code( $code, array $query, array $flow ) {
		$body    = array(
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $this->callback_url(),
		);
		$headers = array( 'Content-Type' => 'application/x-www-form-urlencoded' );
		if ( $this->basic_auth() ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( rawurlencode( $this->option( 'client_id' ) ) . ':' . rawurlencode( $this->option( 'client_secret' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		} else {
			$body['client_id'] = $this->option( 'client_id' );
			if ( '' !== $this->option( 'client_secret' ) ) {
				$body['client_secret'] = $this->option( 'client_secret' );
			}
		}
		if ( $this->use_pkce() ) {
			$body['code_verifier'] = $flow['verifier'];
		}
		$body = array_merge( $body, $this->extra_token_params( $query, $flow ) );

		$token = $this->request_json(
			'POST',
			apply_filters( 'nooksl_endpoint', $this->token_endpoint(), 'token', $this->id() ),
			array(
				'headers' => $headers,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		if ( empty( $token['access_token'] ) ) {
			return $this->error( __( 'The provider did not return an access token.', 'nook-social-login' ) );
		}
		return $token;
	}

	/**
	 * GET request with a bearer token.
	 *
	 * @param string $url     URL.
	 * @param string $token   Access token.
	 * @param array  $headers Extra headers.
	 * @param string $scheme  Authorization scheme.
	 * @return array|WP_Error
	 */
	protected function api_get( $url, $token, array $headers = array(), $scheme = 'Bearer' ) {
		$headers['Authorization'] = $scheme . ' ' . $token;
		return $this->request_json(
			'GET',
			apply_filters( 'nooksl_endpoint', $url, 'userinfo', $this->id() ),
			array( 'headers' => $headers )
		);
	}
}
