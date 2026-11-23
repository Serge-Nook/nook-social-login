<?php
/**
 * Steam OpenID 2.0 provider.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Steam login through OpenID 2.0.
 */
class NookSL_Provider_Steam extends NookSL_Provider {

	const ENDPOINT = 'https://steamcommunity.com/openid/login';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'steam';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Steam', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function color() {
		return '#171A21';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'api_key' => array(
				'label'       => __( 'Steam Web API key (optional)', 'nook-social-login' ),
				'type'        => 'password',
				'description' => __( 'Without a key the nickname and avatar are read from the public community profile.', 'nook-social-login' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function required_fields() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function console_url() {
		return 'https://steamcommunity.com/dev/apikey';
	}

	/**
	 * {@inheritDoc}
	 */
	public function instructions() {
		return __( 'Steam needs no application: just enable it. Steam does not share e-mail addresses, so new users are asked for one. A Web API key is optional.', 'nook-social-login' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $query Request query.
	 */
	public function callback_state( array $query ) {
		return isset( $query['nooksl_state'] ) ? (string) $query['nooksl_state'] : '';
	}

	/**
	 * Return URL including the state.
	 *
	 * @param string $state Flow state.
	 * @return string
	 */
	private function return_to( $state ) {
		return add_query_arg( 'nooksl_state', rawurlencode( $state ), $this->callback_url() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $flow Flow data.
	 */
	public function authorize_url( array $flow ) {
		$home     = wp_parse_url( home_url() );
		$realm    = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . '/';
		$params   = array(
			'openid.ns'         => 'http://specs.openid.net/auth/2.0',
			'openid.mode'       => 'checkid_setup',
			'openid.return_to'  => $this->return_to( $flow['state'] ),
			'openid.realm'      => $realm,
			'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
		);
		$endpoint = apply_filters( 'nooksl_endpoint', self::ENDPOINT, 'authorize', $this->id() );
		return $endpoint . ( false === strpos( $endpoint, '?' ) ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Parses `openid.*` parameters keeping the dots PHP replaces in $_GET.
	 *
	 * @param string $raw Raw query string.
	 * @return array
	 */
	private function openid_params( $raw ) {
		$params = array();
		foreach ( explode( '&', $raw ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$parts = explode( '=', $pair, 2 );
			$key   = urldecode( $parts[0] );
			if ( 0 === strpos( $key, 'openid.' ) ) {
				$params[ $key ] = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';
			}
		}
		return $params;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $query Request query; `__raw` holds the raw query string.
	 * @param array $flow  Flow data.
	 */
	public function handle_callback( array $query, array $flow ) {
		$params = $this->openid_params( isset( $query['__raw'] ) ? (string) $query['__raw'] : '' );
		$mode   = isset( $params['openid.mode'] ) ? $params['openid.mode'] : '';
		if ( 'cancel' === $mode ) {
			return new WP_Error( 'nooksl_cancelled', __( 'Login was cancelled.', 'nook-social-login' ) );
		}
		if ( 'id_res' !== $mode
			|| ! isset( $params['openid.op_endpoint'], $params['openid.return_to'], $params['openid.claimed_id'] )
			|| self::ENDPOINT !== $params['openid.op_endpoint']
			|| $this->return_to( $flow['state'] ) !== $params['openid.return_to']
			|| ! preg_match( '#^https?://steamcommunity\.com/openid/id/(\d{17})$#', $params['openid.claimed_id'], $m )
		) {
			return $this->error( __( 'Invalid response from Steam.', 'nook-social-login' ) );
		}
		$steam_id = $m[1];

		$check                = $params;
		$check['openid.mode'] = 'check_authentication';
		$response             = wp_remote_post(
			apply_filters( 'nooksl_endpoint', self::ENDPOINT, 'verify', $this->id() ),
			apply_filters(
				'nooksl_http_args',
				array(
					'timeout' => 20,
					'body'    => $check,
				),
				self::ENDPOINT,
				$this->id()
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->error( $response->get_error_message() );
		}
		if ( ! preg_match( '/^is_valid\s*:\s*true\s*$/m', wp_remote_retrieve_body( $response ) ) ) {
			return $this->error( __( 'Steam did not confirm the login.', 'nook-social-login' ) );
		}

		$profile = $this->profile(
			array(
				'id'           => $steam_id,
				'login'        => 'steam_' . $steam_id,
				'display_name' => $steam_id,
				'profile_url'  => 'https://steamcommunity.com/profiles/' . $steam_id . '/',
			)
		);
		return $this->fill_profile( $profile );
	}

	/**
	 * Adds nickname and avatar.
	 *
	 * @param array $profile Profile.
	 * @return array
	 */
	private function fill_profile( array $profile ) {
		$key = $this->option( 'api_key' );
		if ( '' !== $key ) {
			$data = $this->request_json(
				'GET',
				add_query_arg(
					array(
						'key'      => rawurlencode( $key ),
						'steamids' => $profile['id'],
					),
					'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/'
				)
			);
			if ( ! is_wp_error( $data ) && ! empty( $data['response']['players'][0] ) ) {
				$player = $data['response']['players'][0];
				if ( ! empty( $player['personaname'] ) ) {
					$profile['display_name'] = (string) $player['personaname'];
					$profile['login']        = (string) $player['personaname'];
				}
				if ( ! empty( $player['avatarfull'] ) ) {
					$profile['avatar'] = (string) $player['avatarfull'];
				}
				return $profile;
			}
		}

		$response = wp_remote_get( $profile['profile_url'] . '?xml=1', array( 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$xml = wp_remote_retrieve_body( $response );
			if ( preg_match( '#<steamID><!\[CDATA\[(.*?)\]\]></steamID>#s', $xml, $m ) && '' !== trim( $m[1] ) ) {
				$profile['display_name'] = trim( $m[1] );
				$profile['login']        = trim( $m[1] );
			}
			if ( preg_match( '#<avatarFull><!\[CDATA\[(.*?)\]\]></avatarFull>#s', $xml, $m ) ) {
				$profile['avatar'] = esc_url_raw( trim( $m[1] ) );
			}
		}
		return $profile;
	}
}
