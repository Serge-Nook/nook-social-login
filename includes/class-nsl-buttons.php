<?php
/**
 * Login buttons and shortcode.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the provider buttons.
 */
class NookSL_Buttons {

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'nook_social_login', array( $this, 'shortcode' ) );

		if ( NookSL_Settings::get( 'show_login' ) ) {
			add_action( 'login_form', array( $this, 'login_form' ) );
			add_action( 'login_footer', array( $this, 'move_below_submit' ) );
		}
		if ( NookSL_Settings::get( 'show_register' ) && NookSL_Settings::get( 'allow_register' ) ) {
			add_action( 'register_form', array( $this, 'register_form' ) );
			add_action( 'login_footer', array( $this, 'move_below_submit' ) );
		}
		if ( NookSL_Settings::get( 'show_comments' ) ) {
			add_action( 'comment_form_must_log_in_after', array( $this, 'comment_form' ) );
			add_action( 'comment_form_top', array( $this, 'comment_form' ) );
		}
		if ( NookSL_Settings::get( 'show_woo' ) ) {
			add_action( 'woocommerce_login_form_end', array( $this, 'woo_form' ) );
			if ( NookSL_Settings::get( 'allow_register' ) ) {
				add_action( 'woocommerce_register_form_end', array( $this, 'woo_form' ) );
			}
		}
	}

	/**
	 * Registers the stylesheet.
	 */
	public function register_assets() {
		wp_register_style( 'nook-social-login', NOOKSL_URL . 'assets/css/nsl.css', array(), NOOKSL_VERSION );
	}

	/**
	 * Enqueues the stylesheet.
	 */
	public function enqueue() {
		if ( ! wp_style_is( 'nook-social-login', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'nook-social-login' );
	}

	/**
	 * URL that starts the login flow.
	 *
	 * @param string $provider    Provider id.
	 * @param string $redirect_to Where to go after login.
	 * @param bool   $link        Link to the current user instead of logging in.
	 * @return string
	 */
	public static function start_url( $provider, $redirect_to = '', $link = false ) {
		$args = array( 'nooksl_start' => $provider );
		if ( '' !== $redirect_to ) {
			$args['redirect_to'] = rawurlencode( $redirect_to );
		}
		if ( $link ) {
			$args['nooksl_link'] = 1;
			$args['_wpnonce']    = wp_create_nonce( 'nooksl_link_' . $provider );
		}
		return add_query_arg( $args, wp_login_url() );
	}

	/**
	 * Current front-end URL.
	 *
	 * @return string
	 */
	public static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url = set_url_scheme( 'http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . $uri );
		$url = remove_query_arg( array( 'nooksl_msg', 'loggedout', 'reauth' ), $url );
		return wp_validate_redirect( $url, home_url( '/' ) );
	}

	/**
	 * Buttons HTML.
	 *
	 * @param array $args context, redirect_to, link, heading, style, providers.
	 * @return string
	 */
	public function render( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'context'     => 'shortcode',
				'redirect_to' => '',
				'link'        => false,
				'heading'     => null,
				'style'       => NookSL_Settings::get( 'button_style' ),
				'providers'   => array(),
			)
		);

		$providers = NookSL_Plugin::instance()->active_providers();
		if ( $args['providers'] ) {
			$providers = array_intersect_key( $providers, array_flip( $args['providers'] ) );
		}
		if ( $args['link'] && is_user_logged_in() ) {
			$providers = array_diff_key( $providers, NookSL_Users::linked( get_current_user_id() ) );
		}
		$providers = apply_filters( 'nooksl_buttons_providers', $providers, $args );
		if ( ! $providers ) {
			return '';
		}
		$this->enqueue();

		if ( null === $args['heading'] ) {
			$args['heading'] = $args['link'] ? __( 'Link a social account', 'nook-social-login' ) : __( 'or log in with', 'nook-social-login' );
		}
		$style = 'icons' === $args['style'] ? 'icons' : 'full';

		$html = '<div class="nsl-wrap nsl-context-' . esc_attr( $args['context'] ) . '">';
		if ( '' !== $args['heading'] ) {
			$html .= '<p class="nsl-heading"><span>' . esc_html( $args['heading'] ) . '</span></p>';
		}
		$html .= '<div class="nsl-buttons nsl-style-' . esc_attr( $style ) . '">';
		foreach ( $providers as $provider ) {
			$text = $args['link']
				/* translators: %s: provider name. */
				? sprintf( __( 'Link %s', 'nook-social-login' ), $provider->label() )
				/* translators: %s: provider name. */
				: sprintf( __( 'Log in with %s', 'nook-social-login' ), $provider->label() );
			$html .= sprintf(
				'<a class="nsl-btn nsl-btn-%1$s" href="%2$s" rel="nofollow" title="%3$s" aria-label="%3$s" style="--nsl-color:%4$s"><span class="nsl-icon">%5$s</span><span class="nsl-label">%6$s</span></a>',
				esc_attr( $provider->id() ),
				esc_url( self::start_url( $provider->id(), $args['redirect_to'], (bool) $args['link'] ) ),
				esc_attr( $text ),
				esc_attr( $provider->color() ),
				NookSL_Icons::svg( $provider->id() ),
				esc_html( 'icons' === $style ? $provider->label() : $text )
			);
		}
		$html .= '</div></div>';
		return apply_filters( 'nooksl_buttons_html', $html, $args );
	}

	/**
	 * Redirect target requested on wp-login.php.
	 *
	 * @return string
	 */
	private function login_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		return '' !== $raw ? wp_validate_redirect( $raw, '' ) : '';
	}

	/**
	 * Buttons on wp-login.php.
	 */
	public function login_form() {
		$html = $this->render(
			array(
				'context'     => 'login',
				'redirect_to' => $this->login_redirect(),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * Moves the buttons on wp-login.php below the submit button.
	 */
	public function move_below_submit() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		document.querySelectorAll( '#loginform .nsl-wrap, #registerform .nsl-wrap' ).forEach( function ( el ) {
			el.parentNode.appendChild( el );
		} );
		</script>
		<?php
	}

	/**
	 * Buttons on the registration form.
	 */
	public function register_form() {
		$html = $this->render(
			array(
				'context'     => 'register',
				'redirect_to' => $this->login_redirect(),
				'heading'     => __( 'or register with', 'nook-social-login' ),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * Buttons above the comment form for guests.
	 */
	public function comment_form() {
		static $done = false;
		if ( $done || is_user_logged_in() ) {
			return;
		}
		$done     = true;
		$redirect = get_permalink();
		$html     = $this->render(
			array(
				'context'     => 'comments',
				'redirect_to' => $redirect ? $redirect . '#respond' : self::current_url(),
				'heading'     => __( 'Log in to comment:', 'nook-social-login' ),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * Buttons on WooCommerce login and registration forms.
	 */
	public function woo_form() {
		$html = $this->render(
			array(
				'context'     => 'woocommerce',
				'redirect_to' => self::current_url(),
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * [nook_social_login] shortcode.
	 *
	 * Attributes: heading, style (full|icons), redirect, providers (comma list), link (1 = linking for logged-in users).
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'heading'   => null,
				'style'     => NookSL_Settings::get( 'button_style' ),
				'redirect'  => '',
				'providers' => '',
				'link'      => '0',
			),
			$atts,
			'nook_social_login'
		);
		$link = in_array( strtolower( (string) $atts['link'] ), array( '1', 'yes', 'true' ), true );
		if ( is_user_logged_in() && ! $link ) {
			return '';
		}
		$html = '';
		$msg  = NookSL_Auth::pull_message();
		if ( $msg ) {
			$html .= '<p class="nsl-message nsl-message-' . esc_attr( $msg['type'] ) . '">' . esc_html( $msg['text'] ) . '</p>';
		}
		return $html . $this->render(
			array(
				'context'     => 'shortcode',
				'redirect_to' => '' !== $atts['redirect'] ? wp_validate_redirect( esc_url_raw( $atts['redirect'] ), '' ) : self::current_url(),
				'link'        => $link && is_user_logged_in(),
				'heading'     => $atts['heading'],
				'style'       => $atts['style'],
				'providers'   => array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['providers'] ) ) ),
			)
		);
	}
}
