<?php
/**
 * Login flow: start, callback, e-mail prompt, sign in.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles requests to wp-login.php made by the plugin.
 */
class NookSL_Auth {

	const COOKIE   = 'nooksl_flow';
	const FLOW_TTL = 900;

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'login_init', array( $this, 'route' ), 1 );
		add_action( 'login_form_nooksl_email', array( $this, 'email_form' ) );
		add_filter( 'wp_login_errors', array( $this, 'login_messages' ) );
	}

	/**
	 * Dispatches start and callback requests.
	 */
	public function route() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth state + browser-bound cookie protect the flow.
		if ( isset( $_GET['nooksl_start'] ) ) {
			$this->start( sanitize_key( wp_unslash( $_GET['nooksl_start'] ) ) );
		} elseif ( isset( $_GET['nooksl_callback'] ) ) {
			$this->callback( sanitize_key( wp_unslash( $_GET['nooksl_callback'] ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Browser token bound to the flow, created on demand.
	 *
	 * @return string
	 */
	private function browser_token() {
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$token = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
			if ( strlen( $token ) >= 32 ) {
				return $token;
			}
		}
		$token = wp_generate_password( 40, false );
		setcookie(
			self::COOKIE,
			$token,
			array(
				'expires'  => time() + DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $token;
		return $token;
	}

	/**
	 * Hash of the browser token stored server side.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private function token_hash( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Checks the browser token against a stored hash.
	 *
	 * @param string $hash Stored hash.
	 * @return bool
	 */
	private function token_matches( $hash ) {
		if ( empty( $_COOKIE[ self::COOKIE ] ) || ! is_string( $hash ) ) {
			return false;
		}
		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return hash_equals( $hash, $this->token_hash( $token ) );
	}

	/**
	 * Validated redirect target from the request.
	 *
	 * @param string $fallback Default.
	 * @return string
	 */
	private function requested_redirect( $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		return '' !== $raw ? wp_validate_redirect( $raw, $fallback ) : $fallback;
	}

	/**
	 * Sends the user to the provider.
	 *
	 * @param string $id Provider id.
	 */
	private function start( $id ) {
		$provider = NookSL_Plugin::instance()->provider( $id );
		if ( ! $provider || ! $provider->is_active() ) {
			$this->fail( __( 'This login method is not available.', 'nook-social-login' ) );
		}

		$link_user = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is checked right below.
		if ( ! empty( $_GET['nooksl_link'] ) ) {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'nooksl_link_' . $id ) ) {
				$this->fail( __( 'The link has expired. Please try again.', 'nook-social-login' ) );
			}
			$link_user = get_current_user_id();
		} elseif ( is_user_logged_in() ) {
			wp_safe_redirect( $this->requested_redirect( admin_url() ) );
			exit;
		}

		$flow = array(
			'provider'    => $id,
			'state'       => wp_generate_password( 32, false ),
			'verifier'    => wp_generate_password( 64, false ),
			'redirect_to' => $this->requested_redirect( $link_user ? admin_url( 'profile.php' ) : '' ),
			'link_user'   => $link_user,
			'token_hash'  => $this->token_hash( $this->browser_token() ),
		);

		$url = $provider->authorize_url( $flow );
		if ( is_wp_error( $url ) ) {
			$this->fail( $url->get_error_message() );
		}
		set_transient( 'nooksl_flow_' . $flow['state'], $flow, self::FLOW_TTL );

		nocache_headers();
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external provider URL.
		wp_redirect( $url );
		exit;
	}

	/**
	 * Handles the provider redirect back.
	 *
	 * @param string $id Provider id.
	 */
	private function callback( $id ) {
		nocache_headers();
		$provider = NookSL_Plugin::instance()->provider( $id );
		if ( ! $provider || ! $provider->is_active() ) {
			$this->fail( __( 'This login method is not available.', 'nook-social-login' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query           = wp_unslash( $_GET );
		$query['__raw']  = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and validated by the provider.
		$state           = preg_replace( '/[^A-Za-z0-9]/', '', $provider->callback_state( $query ) );
		$flow            = '' !== $state ? get_transient( 'nooksl_flow_' . $state ) : false;
		$flow_is_invalid = ! is_array( $flow ) || $flow['provider'] !== $id || ! $this->token_matches( $flow['token_hash'] );
		if ( $flow_is_invalid ) {
			$this->fail( __( 'The login session has expired or was opened in another browser. Please try again.', 'nook-social-login' ) );
		}
		delete_transient( 'nooksl_flow_' . $state );

		$profile = $provider->handle_callback( $query, $flow );
		if ( is_wp_error( $profile ) ) {
			$this->fail( $profile->get_error_message(), $flow );
		}
		$profile = apply_filters( 'nooksl_profile', $profile, $id );
		$this->process( $provider, $profile, $flow );
	}

	/**
	 * Links, signs in or registers the user.
	 *
	 * @param NookSL_Provider $provider Provider.
	 * @param array           $profile  Normalised profile.
	 * @param array           $flow     Flow data.
	 */
	private function process( NookSL_Provider $provider, array $profile, array $flow ) {
		$id     = $provider->id();
		$linked = NookSL_Users::find( $id, $profile['id'] );

		if ( ! empty( $flow['link_user'] ) ) {
			if ( get_current_user_id() !== (int) $flow['link_user'] ) {
				$this->fail( __( 'The link has expired. Please try again.', 'nook-social-login' ) );
			}
			if ( $linked && (int) $linked->ID !== (int) $flow['link_user'] ) {
				/* translators: %s: provider name. */
				$this->fail( sprintf( __( 'This %s account is already linked to another user.', 'nook-social-login' ), $provider->label() ), $flow );
			}
			NookSL_Users::link( (int) $flow['link_user'], $id, $profile );
			/* translators: %s: provider name. */
			$this->redirect_with_message( $flow['redirect_to'], sprintf( __( '%s account linked.', 'nook-social-login' ), $provider->label() ), 'success' );
		}

		if ( $linked ) {
			NookSL_Users::update_profile( $linked->ID, $id, $profile );
			$this->sign_in( $linked, $flow );
		}

		$email = sanitize_email( $profile['email'] );
		if ( '' !== $email && email_exists( $email ) ) {
			$existing = get_user_by( 'email', $email );
			$can_link = $existing && $profile['email_verified'] && NookSL_Settings::get( 'link_by_email' )
				&& ! $this->is_privileged_user( $existing )
				&& ! get_user_meta( $existing->ID, 'nooksl_email_unverified', true )
				&& '' === (string) get_user_meta( $existing->ID, NookSL_Users::id_key( $id ), true );
			if ( $can_link ) {
				NookSL_Users::link( $existing->ID, $id, $profile );
				$this->sign_in( $existing, $flow );
			}
			$this->fail(
				sprintf(
					/* translators: %s: provider name. */
					__( 'An account with this e-mail address already exists. Log in with your password and link %s in your profile.', 'nook-social-login' ),
					$provider->label()
				),
				$flow
			);
		}

		if ( ! NookSL_Settings::get( 'allow_register' ) ) {
			$this->fail( __( 'Registration through social networks is disabled on this site. Log in with your password and link the account in your profile.', 'nook-social-login' ), $flow );
		}

		if ( '' === $email && NookSL_Settings::get( 'ask_email' ) ) {
			$key = wp_generate_password( 32, false );
			set_transient(
				'nooksl_pending_' . $key,
				array(
					'provider'   => $id,
					'profile'    => $profile,
					'flow'       => $flow,
					'token_hash' => $this->token_hash( $this->browser_token() ),
				),
				self::FLOW_TTL
			);
			wp_safe_redirect(
				add_query_arg(
					array(
						'action'     => 'nooksl_email',
						'nooksl_key' => $key,
					),
					wp_login_url()
				)
			);
			exit;
		}

		$this->register_and_sign_in( $id, $profile, $email, $flow );
	}

	/**
	 * Registers a user and signs in.
	 *
	 * @param string $id      Provider id.
	 * @param array  $profile Profile.
	 * @param string $email   E-mail.
	 * @param array  $flow    Flow data.
	 */
	private function register_and_sign_in( $id, array $profile, $email, array $flow ) {
		$user_id = NookSL_Users::register( $id, $profile, $email );
		if ( is_wp_error( $user_id ) ) {
			$this->fail( $user_id->get_error_message(), $flow );
		}
		if ( empty( $profile['email_verified'] ) ) {
			update_user_meta( $user_id, 'nooksl_email_unverified', 1 );
		}
		$flow['new_user'] = true;
		$this->sign_in( get_user_by( 'id', $user_id ), $flow );
	}

	/**
	 * Users that are never linked automatically by e-mail.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	private function is_privileged_user( WP_User $user ) {
		$privileged = user_can( $user, 'manage_options' ) || user_can( $user, 'edit_users' ) || is_super_admin( $user->ID );
		return (bool) apply_filters( 'nooksl_is_privileged_user', $privileged, $user );
	}

	/**
	 * Signs the user in and redirects.
	 *
	 * @param WP_User $user User.
	 * @param array   $flow Flow data.
	 */
	private function sign_in( WP_User $user, array $flow ) {
		$allowed = apply_filters( 'nooksl_allow_login', true, $user, $flow['provider'] );
		if ( is_wp_error( $allowed ) ) {
			$this->fail( $allowed->get_error_message(), $flow );
		}
		if ( ! $allowed || ( is_multisite() && is_user_spammy( $user ) ) ) {
			$this->fail( __( 'Login is not allowed for this account.', 'nook-social-login' ), $flow );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core action.
		do_action( 'nooksl_login', $user->ID, $flow['provider'] );

		$requested = isset( $flow['redirect_to'] ) ? (string) $flow['redirect_to'] : '';
		$setting   = (string) NookSL_Settings::get( 'redirect' );
		$redirect  = '' !== $setting ? $setting : ( '' !== $requested ? $requested : admin_url() );
		$redirect  = apply_filters( 'login_redirect', $redirect, $requested, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		$redirect  = apply_filters( 'nooksl_login_redirect', $redirect, $user, $flow );

		$to_admin = in_array( untrailingslashit( $redirect ), array( untrailingslashit( admin_url() ), 'wp-admin' ), true );
		if ( $to_admin && ! $user->has_cap( 'edit_posts' ) ) {
			$redirect = home_url( '/' );
		}

		wp_safe_redirect( wp_validate_redirect( $redirect, home_url( '/' ) ) );
		exit;
	}

	/**
	 * Redirects with a one-time message.
	 *
	 * @param string $url     Target URL.
	 * @param string $message Message.
	 * @param string $type    error|success.
	 */
	private function redirect_with_message( $url, $message, $type = 'error' ) {
		wp_safe_redirect( self::message_url( '' !== $url ? $url : wp_login_url(), $message, $type ) );
		exit;
	}

	/**
	 * Adds a one-time message to a URL.
	 *
	 * @param string $url     Target URL.
	 * @param string $message Message.
	 * @param string $type    error|success.
	 * @return string
	 */
	public static function message_url( $url, $message, $type = 'error' ) {
		$key = wp_generate_password( 16, false );
		set_transient(
			'nooksl_msg_' . $key,
			array(
				'type' => $type,
				'text' => $message,
			),
			5 * MINUTE_IN_SECONDS
		);
		return add_query_arg( 'nooksl_msg', $key, remove_query_arg( 'nooksl_msg', $url ) );
	}

	/**
	 * Aborts the flow with an error.
	 *
	 * @param string $message Message.
	 * @param array  $flow    Flow data, if known.
	 */
	private function fail( $message, array $flow = array() ) {
		if ( ! empty( $flow['link_user'] ) && ! empty( $flow['redirect_to'] ) ) {
			$this->redirect_with_message( $flow['redirect_to'], $message );
		}
		$login = wp_login_url( ! empty( $flow['redirect_to'] ) ? $flow['redirect_to'] : '' );
		$this->redirect_with_message( $login, $message );
	}

	/**
	 * Reads a one-time message from the request.
	 *
	 * @return array|null type, text.
	 */
	public static function pull_message() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['nooksl_msg'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['nooksl_msg'] ) ) ) : '';
		if ( '' === $key ) {
			return null;
		}
		static $cache = array();
		if ( ! array_key_exists( $key, $cache ) ) {
			$msg           = get_transient( 'nooksl_msg_' . $key );
			$cache[ $key ] = is_array( $msg ) ? $msg : null;
		}
		return $cache[ $key ];
	}

	/**
	 * Shows plugin messages on the login screen.
	 *
	 * @param WP_Error $errors Login errors.
	 * @return WP_Error
	 */
	public function login_messages( $errors ) {
		$msg = self::pull_message();
		if ( $msg ) {
			$errors->add( 'nooksl_message', esc_html( $msg['text'] ), 'success' === $msg['type'] ? 'message' : 'error' );
		}
		return $errors;
	}

	/**
	 * Pending registration bound to this browser.
	 *
	 * @param string $key Pending key.
	 * @return array|null
	 */
	private function pending( $key ) {
		$pending = '' !== $key ? get_transient( 'nooksl_pending_' . $key ) : false;
		if ( ! is_array( $pending ) || ! $this->token_matches( $pending['token_hash'] ) ) {
			return null;
		}
		return $pending;
	}

	/**
	 * Asks for an e-mail address when the provider did not share one.
	 */
	public function email_form() {
		nocache_headers();
		$key     = isset( $_REQUEST['nooksl_key'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_REQUEST['nooksl_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pending = $this->pending( $key );
		if ( ! $pending ) {
			$this->fail( __( 'The login session has expired or was opened in another browser. Please try again.', 'nook-social-login' ) );
		}
		$provider = NookSL_Plugin::instance()->provider( $pending['provider'] );
		$errors   = new WP_Error();
		$email    = '';

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			check_admin_referer( 'nooksl_email_' . $key );
			$email = isset( $_POST['nooksl_email'] ) ? sanitize_email( wp_unslash( $_POST['nooksl_email'] ) ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				$errors->add( 'invalid_email', __( 'Please enter a valid e-mail address.', 'nook-social-login' ) );
			} elseif ( email_exists( $email ) ) {
				$errors->add(
					'email_exists',
					sprintf(
						/* translators: %s: provider name. */
						__( 'An account with this e-mail address already exists. Log in with your password and link %s in your profile.', 'nook-social-login' ),
						$provider ? $provider->label() : ''
					)
				);
			} else {
				delete_transient( 'nooksl_pending_' . $key );
				$this->register_and_sign_in( $pending['provider'], $pending['profile'], $email, $pending['flow'] );
			}
		}

		$name = '' !== $pending['profile']['display_name'] ? $pending['profile']['display_name'] : $pending['profile']['login'];
		login_header( __( 'Enter your e-mail', 'nook-social-login' ), '', $errors );
		$action = add_query_arg(
			array(
				'action'     => 'nooksl_email',
				'nooksl_key' => $key,
			),
			wp_login_url()
		);
		?>
		<form name="nooksl_email_form" id="nooksl_email_form" method="post" action="<?php echo esc_url( $action ); ?>">
			<p class="message">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: user name, 2: provider name. */
						__( 'Hello, %1$s! %2$s did not share your e-mail address. Enter it to finish registration.', 'nook-social-login' ),
						$name,
						$provider ? $provider->label() : ''
					)
				);
				?>
			</p>
			<p>
				<label for="nooksl_email"><?php esc_html_e( 'E-mail', 'nook-social-login' ); ?></label>
				<input type="email" name="nooksl_email" id="nooksl_email" class="input" value="<?php echo esc_attr( $email ); ?>" size="25" autocomplete="email" required autofocus />
			</p>
			<?php wp_nonce_field( 'nooksl_email_' . $key ); ?>
			<p class="submit">
				<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Continue', 'nook-social-login' ); ?>" />
			</p>
		</form>
		<?php
		login_footer( 'nooksl_email' );
		exit;
	}
}
