<?php
/**
 * Battle.net provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Battle.net OAuth 2.0.
 */
class NookSL_Provider_Battlenet extends NookSL_OAuth2_Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'battlenet';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Battle.net', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#148EFF';
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
			'region'        => array(
				'label'   => __( 'Region', 'nook-social-login' ),
				'type'    => 'select',
				'options' => array(
					'global' => __( 'Global (America, Europe, Korea, Taiwan)', 'nook-social-login' ),
					'cn'     => __( 'China', 'nook-social-login' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://develop.battle.net/access/clients';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Create a client in the Blizzard developer portal and add the redirect URL below. Battle.net does not share e-mail addresses, so new users are asked for one.', 'nook-social-login' );
	}

	/**
	 * OAuth host for the selected region.
	 *
	 * @return string
	 */
	private function host() {
		return 'cn' === $this->option( 'region' ) ? 'https://oauth.battlenet.com.cn' : 'https://oauth.battle.net';
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
		return $this->host() . '/token';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function scope() {
		return 'openid';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function basic_auth() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $token Token response.
	 * @param array $query Callback query.
	 */
	protected function fetch_profile( array $token, array $query ) {
		$data = $this->api_get( $this->host() . '/userinfo', $token['access_token'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id  = isset( $data['id'] ) ? (string) $data['id'] : ( isset( $data['sub'] ) ? (string) $data['sub'] : '' );
		$tag = isset( $data['battletag'] ) ? (string) $data['battletag'] : '';
		return $this->profile(
			array(
				'id'           => $id,
				'login'        => '' !== $tag ? strtok( $tag, '#' ) : 'battlenet_' . $id,
				'display_name' => '' !== $tag ? $tag : $id,
			)
		);
	}
}
