<?php
/**
 * Twitch provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Twitch OAuth 2.0.
 */
class NookSL_Provider_Twitch extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'twitch';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Twitch', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#9146FF';
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
		return 'https://dev.twitch.tv/console/apps';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Register an application in the Twitch developer console (category "Website Integration", client type "Confidential") and add the redirect URL below as OAuth Redirect URL.', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function authorize_endpoint() {
		return 'https://id.twitch.tv/oauth2/authorize';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function token_endpoint() {
		return 'https://id.twitch.tv/oauth2/token';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function scope() {
		return 'user:read:email';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$data = $this->api_get( 'https://api.twitch.tv/helix/users', $token['access_token'], array( 'Client-Id' => $this->option( 'client_id' ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['data'][0]['id'] ) ) {
			return $this->error( __( 'The provider did not return a user ID.', 'nook-social-login' ) );
		}
		$user  = $data['data'][0];
		$email = isset( $user['email'] ) ? (string) $user['email'] : '';
		$login = isset( $user['login'] ) ? (string) $user['login'] : '';
		return $this->profile(
			array(
				'id'             => (string) $user['id'],
				'email'          => $email,
				'email_verified' => '' !== $email,
				'login'          => $login,
				'display_name'   => ! empty( $user['display_name'] ) ? (string) $user['display_name'] : $login,
				'avatar'         => isset( $user['profile_image_url'] ) ? (string) $user['profile_image_url'] : '',
				'profile_url'    => '' !== $login ? 'https://www.twitch.tv/' . $login : '',
			)
		);
	}
}
