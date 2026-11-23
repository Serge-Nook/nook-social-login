<?php
/**
 * Plugin bootstrap.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin object.
 */
final class NookSL_Plugin {

	/**
	 * Singleton.
	 *
	 * @var NookSL_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Providers keyed by id.
	 *
	 * @var NookSL_Provider[]|null
	 */
	private $providers = null;

	/**
	 * Singleton accessor.
	 *
	 * @return NookSL_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Registers components.
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'update_option_' . NookSL_Settings::OPTION, array( 'NookSL_Settings', 'flush' ) );
		add_action( 'add_option_' . NookSL_Settings::OPTION, array( 'NookSL_Settings', 'flush' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NOOKSL_FILE ), array( $this, 'action_links' ) );

		new NookSL_Auth();
		new NookSL_Buttons();
		new NookSL_Profile();
		if ( is_admin() ) {
			new NookSL_Admin();
		}
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'nook-social-login', false, dirname( plugin_basename( NOOKSL_FILE ) ) . '/languages' );
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=nook-social-login' ) ) . '">' . esc_html__( 'Settings', 'nook-social-login' ) . '</a>' );
		return $links;
	}

	/**
	 * All providers.
	 *
	 * @return NookSL_Provider[]
	 */
	public function providers() {
		if ( null === $this->providers ) {
			$list = apply_filters(
				'nooksl_providers',
				array(
					new NookSL_Provider_VK(),
					new NookSL_Provider_Steam(),
					new NookSL_Provider_Battlenet(),
					new NookSL_Provider_Yandex(),
					new NookSL_Provider_Google(),
					new NookSL_Provider_Twitch(),
					new NookSL_Provider_Discord(),
				)
			);

			$this->providers = array();
			foreach ( $list as $provider ) {
				if ( $provider instanceof NookSL_Provider ) {
					$this->providers[ $provider->id() ] = $provider;
				}
			}
		}
		return $this->providers;
	}

	/**
	 * Provider by id.
	 *
	 * @param string $id Provider id.
	 * @return NookSL_Provider|null
	 */
	public function provider( $id ) {
		$all = $this->providers();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Enabled and configured providers.
	 *
	 * @return NookSL_Provider[]
	 */
	public function active_providers() {
		return array_filter(
			$this->providers(),
			static function ( $provider ) {
				return $provider->is_active();
			}
		);
	}
}
