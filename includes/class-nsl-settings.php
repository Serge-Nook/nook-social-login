<?php
/**
 * Plugin settings storage.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and normalises the `nooksl_settings` option.
 */
class NookSL_Settings {

	const OPTION = 'nooksl_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default general settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'allow_register' => 1,
			'default_role'   => 'subscriber',
			'ask_email'      => 1,
			'link_by_email'  => 1,
			'redirect'       => '',
			'show_login'     => 1,
			'show_register'  => 1,
			'show_comments'  => 1,
			'show_woo'       => 1,
			'button_style'   => 'full',
			'use_avatar'     => 1,
			'delete_data'    => 0,
			'providers'      => array(),
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved = get_option( self::OPTION, array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$cache = array_merge( self::defaults(), $saved );
			if ( ! is_array( self::$cache['providers'] ) ) {
				self::$cache['providers'] = array();
			}
		}
		return self::$cache;
	}

	/**
	 * Single general setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Settings of one provider.
	 *
	 * @param string $id Provider id.
	 * @return array
	 */
	public static function provider( $id ) {
		$all = self::all();
		return isset( $all['providers'][ $id ] ) && is_array( $all['providers'][ $id ] ) ? $all['providers'][ $id ] : array();
	}

	/**
	 * Drops the cache after the option changes.
	 */
	public static function flush() {
		self::$cache = null;
	}
}
