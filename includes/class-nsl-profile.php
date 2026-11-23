<?php
/**
 * Linked accounts in the user profile and avatars.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Profile screen integration.
 */
class NookSL_Profile {

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'section' ) );
		add_action( 'edit_user_profile', array( $this, 'section' ) );
		add_action( 'admin_post_nooksl_unlink', array( $this, 'unlink' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'pre_get_avatar_data', array( $this, 'avatar' ), 10, 2 );
	}

	/**
	 * Linked accounts table.
	 *
	 * @param WP_User $user Edited user.
	 */
	public function section( $user ) {
		$plugin    = NookSL_Plugin::instance();
		$linked    = NookSL_Users::linked( $user->ID );
		$own       = get_current_user_id() === (int) $user->ID;
		$providers = array();
		foreach ( $plugin->providers() as $id => $provider ) {
			if ( $provider->is_active() || isset( $linked[ $id ] ) ) {
				$providers[ $id ] = $provider;
			}
		}
		if ( ! $providers ) {
			return;
		}
		wp_enqueue_style( 'nook-social-login', NOOKSL_URL . 'assets/css/nsl.css', array(), NOOKSL_VERSION );
		?>
		<h2 id="nsl-accounts"><?php esc_html_e( 'Social accounts', 'nook-social-login' ); ?></h2>
		<table class="form-table nsl-profile" role="presentation">
			<?php foreach ( $providers as $id => $provider ) : ?>
				<tr>
					<th scope="row">
						<span class="nsl-profile-icon" style="--nsl-color:<?php echo esc_attr( $provider->color() ); ?>"><?php echo NookSL_Icons::svg( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<?php echo esc_html( $provider->label() ); ?>
					</th>
					<td>
						<?php if ( isset( $linked[ $id ] ) ) : ?>
							<?php
							$data = $linked[ $id ];
							$name = ! empty( $data['name'] ) ? $data['name'] : $data['id'];
							?>
							<span class="nsl-linked">
								<?php esc_html_e( 'Linked:', 'nook-social-login' ); ?>
								<?php if ( ! empty( $data['profile_url'] ) ) : ?>
									<a href="<?php echo esc_url( $data['profile_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $name ); ?></a>
								<?php else : ?>
									<strong><?php echo esc_html( $name ); ?></strong>
								<?php endif; ?>
							</span>
							<?php
							$unlink = wp_nonce_url(
								add_query_arg(
									array(
										'action'   => 'nooksl_unlink',
										'provider' => $id,
										'user_id'  => $user->ID,
									),
									admin_url( 'admin-post.php' )
								),
								'nooksl_unlink_' . $id . '_' . $user->ID
							);
							?>
							<a class="button" href="<?php echo esc_url( $unlink ); ?>"><?php esc_html_e( 'Unlink', 'nook-social-login' ); ?></a>
						<?php elseif ( $own && $provider->is_active() ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( NookSL_Buttons::start_url( $id, admin_url( 'profile.php#nsl-accounts' ), true ) ); ?>">
								<?php
								/* translators: %s: provider name. */
								echo esc_html( sprintf( __( 'Link %s', 'nook-social-login' ), $provider->label() ) );
								?>
							</a>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Not linked', 'nook-social-login' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Unlinks a provider.
	 */
	public function unlink() {
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$user_id  = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		check_admin_referer( 'nooksl_unlink_' . $provider . '_' . $user_id );
		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.', 'nook-social-login' ), 403 );
		}

		$back = get_current_user_id() === $user_id ? admin_url( 'profile.php' ) : add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );
		$back = wp_get_referer() ? wp_get_referer() : $back;
		$user = get_user_by( 'id', $user_id );
		$left = array_diff_key( NookSL_Users::linked( $user_id ), array( $provider => true ) );

		if ( $user && '' === $user->user_email && ! $left && get_user_meta( $user_id, 'nooksl_registered_with', true ) ) {
			$msg = __( 'This is the only way to log in to this account. Add an e-mail address first so that a password can be set.', 'nook-social-login' );
			wp_safe_redirect( NookSL_Auth::message_url( $back, $msg ) );
			exit;
		}

		NookSL_Users::unlink( $user_id, $provider );
		$label = NookSL_Plugin::instance()->provider( $provider ) ? NookSL_Plugin::instance()->provider( $provider )->label() : $provider;
		/* translators: %s: provider name. */
		wp_safe_redirect( NookSL_Auth::message_url( $back, sprintf( __( '%s account unlinked.', 'nook-social-login' ), $label ), 'success' ) );
		exit;
	}

	/**
	 * One-time messages in the admin.
	 */
	public function notices() {
		$msg = NookSL_Auth::pull_message();
		if ( ! $msg ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'success' === $msg['type'] ? 'success' : 'error',
			esc_html( $msg['text'] )
		);
	}

	/**
	 * Uses the social network avatar.
	 *
	 * @param array $args        Avatar data.
	 * @param mixed $id_or_email User id, e-mail, WP_User, WP_Post or WP_Comment.
	 * @return array
	 */
	public function avatar( $args, $id_or_email ) {
		if ( ! NookSL_Settings::get( 'use_avatar' ) || ! empty( $args['force_default'] ) ) {
			return $args;
		}
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user    = get_user_by( 'email', $id_or_email );
			$user_id = $user ? $user->ID : 0;
		}
		if ( ! $user_id ) {
			return $args;
		}
		$url = get_user_meta( $user_id, 'nooksl_avatar', true );
		if ( $url ) {
			$args['url']          = esc_url( $url );
			$args['found_avatar'] = true;
		}
		return $args;
	}
}
