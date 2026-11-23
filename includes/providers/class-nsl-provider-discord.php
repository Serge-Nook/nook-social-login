<?php
/**
 * Discord provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discord OAuth 2.0.
 */
class NookSL_Provider_Discord extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'discord';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Discord', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#5865F2';
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
		return 'https://discord.com/developers/applications';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Create an application in the Discord Developer Portal, open OAuth2, copy Client ID and Client Secret and add the redirect URL below to Redirects.', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function authorize_endpoint() {
		return 'https://discord.com/oauth2/authorize';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function token_endpoint() {
		return 'https://discord.com/api/oauth2/token';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function scope() {
		return 'identify email';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function extra_authorize_params() {
		return array( 'prompt' => 'none' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$user = $this->api_get( 'https://discord.com/api/users/@me', $token['access_token'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$id     = isset( $user['id'] ) ? (string) $user['id'] : '';
		$login  = isset( $user['username'] ) ? (string) $user['username'] : '';
		$avatar = '';
		if ( ! empty( $user['avatar'] ) && '' !== $id ) {
			$avatar = 'https://cdn.discordapp.com/avatars/' . rawurlencode( $id ) . '/' . rawurlencode( $user['avatar'] ) . '.png?size=256';
		}
		return $this->profile(
			array(
				'id'             => $id,
				'email'          => isset( $user['email'] ) ? (string) $user['email'] : '',
				'email_verified' => ! empty( $user['verified'] ),
				'login'          => $login,
				'display_name'   => ! empty( $user['global_name'] ) ? (string) $user['global_name'] : $login,
				'avatar'         => $avatar,
			)
		);
	}
}
