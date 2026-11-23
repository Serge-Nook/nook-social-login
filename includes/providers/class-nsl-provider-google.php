<?php
/**
 * Google provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google OpenID Connect.
 */
class NookSL_Provider_Google extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Google', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#FFFFFF';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'client_id'     => array(
				'label' => __( 'Client ID', 'nook-social-login' ),
				'type'  => 'text',
			),
			'client_secret' => array(
				'label' => __( 'Client secret', 'nook-social-login' ),
				'type'  => 'password',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://console.cloud.google.com/apis/credentials';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'In Google Cloud Console configure the OAuth consent screen, then create an "OAuth client ID" of type "Web application" and add the redirect URL below to "Authorized redirect URIs".', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function authorize_endpoint() {
		return 'https://accounts.google.com/o/oauth2/v2/auth';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function token_endpoint() {
		return 'https://oauth2.googleapis.com/token';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function scope() {
		return 'openid email profile';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function use_pkce() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extra_authorize_params() {
		return array( 'prompt' => 'select_account' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$data = $this->api_get( 'https://openidconnect.googleapis.com/v1/userinfo', $token['access_token'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$email = isset( $data['email'] ) ? (string) $data['email'] : '';
		return $this->profile(
			array(
				'id'             => isset( $data['sub'] ) ? (string) $data['sub'] : '',
				'email'          => $email,
				'email_verified' => ! empty( $data['email_verified'] ) && 'false' !== $data['email_verified'],
				'login'          => '' !== $email ? strstr( $email, '@', true ) : '',
				'display_name'   => isset( $data['name'] ) ? (string) $data['name'] : '',
				'first_name'     => isset( $data['given_name'] ) ? (string) $data['given_name'] : '',
				'last_name'      => isset( $data['family_name'] ) ? (string) $data['family_name'] : '',
				'avatar'         => isset( $data['picture'] ) ? (string) $data['picture'] : '',
			)
		);
	}
}
