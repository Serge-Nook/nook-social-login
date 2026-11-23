<?php
/**
 * Linking social accounts to WordPress users.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * User lookup, linking and registration.
 */
class NookSL_Users {

	/**
	 * Meta key that stores the provider user id.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function id_key( $provider ) {
		return 'nooksl_' . $provider . '_id';
	}

	/**
	 * Meta key that stores the provider profile.
	 *
	 * @param string $provider Provider id.
	 * @return string
	 */
	public static function data_key( $provider ) {
		return 'nooksl_' . $provider . '_data';
	}

	/**
	 * Finds the user linked to a provider account.
	 *
	 * @param string $provider Provider id.
	 * @param string $id       Provider user id.
	 * @return WP_User|null
	 */
	public static function find( $provider, $id ) {
		$users = get_users(
			array(
				'meta_key'    => self::id_key( $provider ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => (string) $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'      => 1,
				'count_total' => false,
				'blog_id'     => 0,
			)
		);
		return $users ? $users[0] : null;
	}

	/**
	 * Provider ids linked to a user.
	 *
	 * @param int $user_id User id.
	 * @return array provider => profile data.
	 */
	public static function linked( $user_id ) {
		$out = array();
		foreach ( NookSL_Plugin::instance()->providers() as $provider ) {
			$id = get_user_meta( $user_id, self::id_key( $provider->id() ), true );
			if ( '' !== (string) $id ) {
				$data                   = get_user_meta( $user_id, self::data_key( $provider->id() ), true );
				$out[ $provider->id() ] = is_array( $data ) ? $data : array( 'id' => $id );
			}
		}
		return $out;
	}

	/**
	 * Links a provider account to a user and stores the profile.
	 *
	 * @param int    $user_id  User id.
	 * @param string $provider Provider id.
	 * @param array  $profile  Normalised profile.
	 */
	public static function link( $user_id, $provider, array $profile ) {
		update_user_meta( $user_id, self::id_key( $provider ), (string) $profile['id'] );
		self::update_profile( $user_id, $provider, $profile );
		do_action( 'nooksl_linked', $user_id, $provider, $profile );
	}

	/**
	 * Refreshes the stored profile data.
	 *
	 * @param int    $user_id  User id.
	 * @param string $provider Provider id.
	 * @param array  $profile  Normalised profile.
	 */
	public static function update_profile( $user_id, $provider, array $profile ) {
		update_user_meta(
			$user_id,
			self::data_key( $provider ),
			array(
				'id'           => (string) $profile['id'],
				'name'         => sanitize_text_field( '' !== $profile['display_name'] ? $profile['display_name'] : $profile['login'] ),
				'avatar'       => esc_url_raw( $profile['avatar'] ),
				'profile_url'  => esc_url_raw( $profile['profile_url'] ),
				'linked_since' => self::linked_since( $user_id, $provider ),
			)
		);
		if ( '' !== $profile['avatar'] ) {
			update_user_meta( $user_id, 'nooksl_avatar', esc_url_raw( $profile['avatar'] ) );
			update_user_meta( $user_id, 'nooksl_avatar_provider', $provider );
		}
	}

	/**
	 * Original link time.
	 *
	 * @param int    $user_id  User id.
	 * @param string $provider Provider id.
	 * @return int
	 */
	private static function linked_since( $user_id, $provider ) {
		$data = get_user_meta( $user_id, self::data_key( $provider ), true );
		return is_array( $data ) && ! empty( $data['linked_since'] ) ? (int) $data['linked_since'] : time();
	}

	/**
	 * Removes a provider link.
	 *
	 * @param int    $user_id  User id.
	 * @param string $provider Provider id.
	 */
	public static function unlink( $user_id, $provider ) {
		delete_user_meta( $user_id, self::id_key( $provider ) );
		delete_user_meta( $user_id, self::data_key( $provider ) );
		if ( get_user_meta( $user_id, 'nooksl_avatar_provider', true ) === $provider ) {
			delete_user_meta( $user_id, 'nooksl_avatar' );
			delete_user_meta( $user_id, 'nooksl_avatar_provider' );
			foreach ( self::linked( $user_id ) as $other => $data ) {
				if ( ! empty( $data['avatar'] ) ) {
					update_user_meta( $user_id, 'nooksl_avatar', $data['avatar'] );
					update_user_meta( $user_id, 'nooksl_avatar_provider', $other );
					break;
				}
			}
		}
		do_action( 'nooksl_unlinked', $user_id, $provider );
	}

	/**
	 * Creates a new user from a profile.
	 *
	 * @param string $provider Provider id.
	 * @param array  $profile  Normalised profile.
	 * @param string $email    E-mail address (may be empty).
	 * @return int|WP_Error User id.
	 */
	public static function register( $provider, array $profile, $email ) {
		$role = (string) NookSL_Settings::get( 'default_role' );
		if ( ! get_role( $role ) || self::is_privileged_role( $role ) ) {
			$role = get_option( 'default_role', 'subscriber' );
		}
		$display  = sanitize_text_field( '' !== $profile['display_name'] ? $profile['display_name'] : $profile['login'] );
		$userdata = array(
			'user_login'   => self::unique_login( $provider, $profile, $email ),
			'user_pass'    => wp_generate_password( 32, true, true ),
			'user_email'   => $email,
			'display_name' => $display,
			'nickname'     => $display,
			'first_name'   => sanitize_text_field( $profile['first_name'] ),
			'last_name'    => sanitize_text_field( $profile['last_name'] ),
			'role'         => $role,
		);
		$userdata = apply_filters( 'nooksl_new_user_data', $userdata, $provider, $profile );
		$user_id  = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'nooksl_registered_with', $provider );
		self::link( $user_id, $provider, $profile );

		wp_new_user_notification( $user_id, null, '' !== $email ? 'both' : 'admin' );
		do_action( 'nooksl_registered', $user_id, $provider, $profile );
		return $user_id;
	}

	/**
	 * Roles new users must never get automatically.
	 *
	 * @param string $role Role name.
	 * @return bool
	 */
	public static function is_privileged_role( $role ) {
		$obj = get_role( $role );
		if ( ! $obj ) {
			return true;
		}
		foreach ( array( 'manage_options', 'edit_users', 'promote_users', 'edit_theme_options', 'activate_plugins', 'unfiltered_html' ) as $cap ) {
			if ( $obj->has_cap( $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Transliterates Cyrillic to Latin for user names.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function translit( $text ) {
		$map  = array(
			'а' => 'a',
			'б' => 'b',
			'в' => 'v',
			'г' => 'g',
			'д' => 'd',
			'е' => 'e',
			'ё' => 'yo',
			'ж' => 'zh',
			'з' => 'z',
			'и' => 'i',
			'й' => 'y',
			'к' => 'k',
			'л' => 'l',
			'м' => 'm',
			'н' => 'n',
			'о' => 'o',
			'п' => 'p',
			'р' => 'r',
			'с' => 's',
			'т' => 't',
			'у' => 'u',
			'ф' => 'f',
			'х' => 'h',
			'ц' => 'ts',
			'ч' => 'ch',
			'ш' => 'sh',
			'щ' => 'sch',
			'ъ' => '',
			'ы' => 'y',
			'ь' => '',
			'э' => 'e',
			'ю' => 'yu',
			'я' => 'ya',
			'і' => 'i',
			'ї' => 'yi',
			'є' => 'ye',
			'ґ' => 'g',
		);
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		return remove_accents( strtr( $text, $map ) );
	}

	/**
	 * Generates a free user_login.
	 *
	 * @param string $provider Provider id.
	 * @param array  $profile  Normalised profile.
	 * @param string $email    E-mail address.
	 * @return string
	 */
	private static function unique_login( $provider, array $profile, $email ) {
		$candidates = array( $profile['login'], $profile['display_name'], trim( $profile['first_name'] . ' ' . $profile['last_name'] ) );
		if ( '' !== $email ) {
			$candidates[] = strstr( $email, '@', true );
		}
		$base = '';
		foreach ( $candidates as $candidate ) {
			$candidate = sanitize_user( self::translit( (string) $candidate ), true );
			$candidate = trim( preg_replace( '/[^a-z0-9_.\-]+/', '_', strtolower( $candidate ) ), '_.-' );
			if ( strlen( $candidate ) >= 3 && ! is_numeric( $candidate ) ) {
				$base = substr( $candidate, 0, 50 );
				break;
			}
		}
		if ( '' === $base ) {
			$base = $provider . '_' . substr( preg_replace( '/[^a-z0-9]/i', '', (string) $profile['id'] ), 0, 30 );
		}
		$base = apply_filters( 'nooksl_user_login', $base, $provider, $profile );

		$login  = $base;
		$suffix = 1;
		while ( username_exists( $login ) || in_array( strtolower( $login ), array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) ), true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			++$suffix;
			$login = $base . $suffix;
		}
		return $login;
	}
}
