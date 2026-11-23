<?php
/**
 * VK ID (id.vk.ru) provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * VK ID OAuth 2.1 with PKCE.
 */
class NookSL_Provider_VK extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'vk';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'VK ID', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#0077FF';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'client_id' => array(
				'label' => __( 'App ID', 'nook-social-login' ),
				'type'  => 'text',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function required_fields() {
		return array( 'client_id' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://id.vk.ru/about/business/go';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Create a "Web" application in VK ID for business, enable the e-mail permission, set the base domain of the site and add the redirect URL below to the trusted redirect URLs. Copy the App ID.', 'nook-social-login' );
	}

	/**
	 * VK ID host.
	 *
	 * @return string
	 */
	private function host() {
		return apply_filters( 'nooksl_vk_host', 'https://id.vk.ru' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function authorize_endpoint() {
		return $this->host() . '/authorize';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function token_endpoint() {
		return $this->host() . '/oauth2/auth';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function scope() {
		return 'vkid.personal_info email';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function use_pkce() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $query Callback query.
	 * @param array $flow  Flow data.
	 */
	protected function extra_token_params( array $query, array $flow ) {
		return array(
			'device_id' => isset( $query['device_id'] ) ? (string) $query['device_id'] : '',
			'state'     => $flow['state'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$data = $this->request_json(
			'POST',
			apply_filters( 'nooksl_endpoint', $this->host() . '/oauth2/user_info', 'userinfo', $this->id() ),
			array(
				'body' => array(
					'client_id'    => $this->option( 'client_id' ),
					'access_token' => $token['access_token'],
				),
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['user']['user_id'] ) ) {
			return $this->error( __( 'The provider did not return a user ID.', 'nook-social-login' ) );
		}
		$user  = $data['user'];
		$first = isset( $user['first_name'] ) ? (string) $user['first_name'] : '';
		$last  = isset( $user['last_name'] ) ? (string) $user['last_name'] : '';
		$email = ! empty( $user['email'] ) ? (string) $user['email'] : ( ! empty( $token['email'] ) ? (string) $token['email'] : '' );
		return $this->profile(
			array(
				'id'             => (string) $user['user_id'],
				'email'          => $email,
				'email_verified' => '' !== $email,
				'login'          => 'id' . $user['user_id'],
				'display_name'   => trim( $first . ' ' . $last ),
				'first_name'     => $first,
				'last_name'      => $last,
				'avatar'         => isset( $user['avatar'] ) ? (string) $user['avatar'] : '',
				'profile_url'    => 'https://vk.ru/id' . $user['user_id'],
			)
		);
	}
}
