<?php
/**
 * Yandex ID provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Yandex ID OAuth 2.0.
 */
class NookSL_Provider_Yandex extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'yandex';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Yandex ID', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#FC3F1D';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'client_id'     => array(
				'label' => __( 'ClientID', 'nook-social-login' ),
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
		return 'https://oauth.yandex.ru/client/new';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Create a web service application in Yandex OAuth, grant access to the e-mail address, user name and avatar, and add the redirect URL below as Redirect URI.', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function authorize_endpoint() {
		return 'https://oauth.yandex.ru/authorize';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function token_endpoint() {
		return 'https://oauth.yandex.ru/token';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extra_authorize_params() {
		return array( 'force_confirm' => 'no' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$data = $this->api_get( 'https://login.yandex.ru/info?format=json', $token['access_token'], array(), 'OAuth' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$email  = ! empty( $data['default_email'] ) ? (string) $data['default_email'] : '';
		$avatar = '';
		if ( empty( $data['is_avatar_empty'] ) && ! empty( $data['default_avatar_id'] ) ) {
			$avatar = 'https://avatars.yandex.net/get-yapic/' . rawurlencode( $data['default_avatar_id'] ) . '/islands-200';
		}
		return $this->profile(
			array(
				'id'             => isset( $data['id'] ) ? (string) $data['id'] : '',
				'email'          => $email,
				'email_verified' => '' !== $email,
				'login'          => isset( $data['login'] ) ? (string) $data['login'] : '',
				'display_name'   => ! empty( $data['display_name'] ) ? (string) $data['display_name'] : ( isset( $data['real_name'] ) ? (string) $data['real_name'] : '' ),
				'first_name'     => isset( $data['first_name'] ) ? (string) $data['first_name'] : '',
				'last_name'      => isset( $data['last_name'] ) ? (string) $data['last_name'] : '',
				'avatar'         => $avatar,
			)
		);
	}
}
