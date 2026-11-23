<?php
/**
 * Settings screen.
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Social Login.
 */
class NookSL_Admin {

	const PAGE = 'nook-social-login';

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the menu entry.
	 */
	public function menu() {
		add_options_page(
			__( 'Social Login', 'nook-social-login' ),
			__( 'Social Login', 'nook-social-login' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the option.
	 */
	public function register() {
		register_setting(
			'nooksl',
			NookSL_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => NookSL_Settings::defaults(),
			)
		);
	}

	/**
	 * Admin assets for the settings page.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nook-social-login', NOOKSL_URL . 'assets/css/nsl.css', array(), NOOKSL_VERSION );
		wp_enqueue_style( 'nook-social-login-admin', NOOKSL_URL . 'assets/css/admin.css', array(), NOOKSL_VERSION );
		wp_enqueue_script( 'nook-social-login-admin', NOOKSL_URL . 'assets/js/admin.js', array(), NOOKSL_VERSION, true );
		wp_localize_script( 'nook-social-login-admin', 'nslAdmin', array( 'copied' => __( 'Copied', 'nook-social-login' ) ) );
	}

	/**
	 * Roles a new user may get.
	 *
	 * @return array role => name
	 */
	private function roles() {
		$out = array();
		foreach ( wp_roles()->get_names() as $role => $name ) {
			if ( ! NookSL_Users::is_privileged_role( $role ) ) {
				$out[ $role ] = translate_user_role( $name );
			}
		}
		return $out;
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = NookSL_Settings::defaults();
		$out      = array();

		foreach ( array( 'allow_register', 'ask_email', 'link_by_email', 'show_login', 'show_register', 'show_comments', 'show_woo', 'use_avatar', 'delete_data' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		$role                = isset( $input['default_role'] ) ? sanitize_key( $input['default_role'] ) : $defaults['default_role'];
		$out['default_role'] = array_key_exists( $role, $this->roles() ) ? $role : 'subscriber';
		$out['button_style'] = isset( $input['button_style'] ) && 'icons' === $input['button_style'] ? 'icons' : 'full';
		$out['redirect']     = isset( $input['redirect'] ) ? esc_url_raw( trim( (string) $input['redirect'] ) ) : '';

		$out['providers'] = array();
		foreach ( NookSL_Plugin::instance()->providers() as $id => $provider ) {
			$raw  = isset( $input['providers'][ $id ] ) && is_array( $input['providers'][ $id ] ) ? $input['providers'][ $id ] : array();
			$opts = array( 'enabled' => empty( $raw['enabled'] ) ? '0' : '1' );
			foreach ( $provider->fields() as $key => $field ) {
				$value = isset( $raw[ $key ] ) ? trim( sanitize_text_field( (string) $raw[ $key ] ) ) : '';
				if ( 'select' === $field['type'] && ! array_key_exists( $value, $field['options'] ) ) {
					$value = (string) key( $field['options'] );
				}
				$opts[ $key ] = $value;
			}
			$out['providers'][ $id ] = $opts;
		}
		return $out;
	}

	/**
	 * Field name attribute.
	 *
	 * @param string ...$path Keys.
	 * @return string
	 */
	private function name( ...$path ) {
		return NookSL_Settings::OPTION . '[' . implode( '][', $path ) . ']';
	}

	/**
	 * Checkbox row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param string $desc  Description.
	 */
	private function checkbox( $key, $label, $desc = '' ) {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="1" <?php checked( (int) NookSL_Settings::get( $key ), 1 ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( '' !== $desc ) : ?>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<br />
		<?php
	}

	/**
	 * Renders the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$providers = NookSL_Plugin::instance()->providers();
		?>
		<div class="wrap nsl-admin">
			<h1><?php esc_html_e( 'Social Login', 'nook-social-login' ); ?></h1>
			<p><?php esc_html_e( 'Let visitors register and log in with VK ID, Steam, Battle.net, Yandex ID, Google, Twitch and Discord. Enable a network, create an application in its developer console with the redirect URL shown below and paste the keys.', 'nook-social-login' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'nooksl' ); ?>

				<h2><?php esc_html_e( 'Social networks', 'nook-social-login' ); ?></h2>
				<div class="nsl-cards">
					<?php foreach ( $providers as $id => $provider ) : ?>
						<?php $this->provider_card( $provider ); ?>
					<?php endforeach; ?>
				</div>

				<h2><?php esc_html_e( 'General', 'nook-social-login' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Registration', 'nook-social-login' ); ?></th>
						<td>
							<?php
							$this->checkbox( 'allow_register', __( 'Create a new account on the first login', 'nook-social-login' ), __( 'Works even when "Anyone can register" is turned off in General Settings.', 'nook-social-login' ) );
							$this->checkbox( 'ask_email', __( 'Ask for an e-mail when the network does not share it (Steam, Battle.net)', 'nook-social-login' ) );
							$this->checkbox( 'link_by_email', __( 'Link to an existing account with the same confirmed e-mail', 'nook-social-login' ), __( 'Administrators and user managers are never linked automatically: they link networks in their profile.', 'nook-social-login' ) );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsl-role"><?php esc_html_e( 'Role of new users', 'nook-social-login' ); ?></label></th>
						<td>
							<select id="nsl-role" name="<?php echo esc_attr( $this->name( 'default_role' ) ); ?>">
								<?php foreach ( $this->roles() as $role => $name ) : ?>
									<option value="<?php echo esc_attr( $role ); ?>" <?php selected( NookSL_Settings::get( 'default_role' ), $role ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsl-redirect"><?php esc_html_e( 'Redirect after login', 'nook-social-login' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="nsl-redirect" name="<?php echo esc_attr( $this->name( 'redirect' ) ); ?>" value="<?php echo esc_attr( NookSL_Settings::get( 'redirect' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty to return the user to the page where the button was clicked.', 'nook-social-login' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show buttons', 'nook-social-login' ); ?></th>
						<td>
							<?php
							$this->checkbox( 'show_login', __( 'On the login form', 'nook-social-login' ) );
							$this->checkbox( 'show_register', __( 'On the registration form', 'nook-social-login' ) );
							$this->checkbox( 'show_comments', __( 'Above the comment form for guests', 'nook-social-login' ) );
							$this->checkbox( 'show_woo', __( 'On WooCommerce login and registration forms', 'nook-social-login' ) );
							?>
							<p class="description">
								<?php
								printf(
									/* translators: 1: shortcode, 2: shortcode with attributes. */
									esc_html__( 'Anywhere else: shortcode %1$s, for example %2$s. Use link="1" to let logged-in users link more networks.', 'nook-social-login' ),
									'<code>[nook_social_login]</code>',
									'<code>[nook_social_login style="icons" providers="vk,google" heading=""]</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="nsl-style"><?php esc_html_e( 'Button style', 'nook-social-login' ); ?></label></th>
						<td>
							<select id="nsl-style" name="<?php echo esc_attr( $this->name( 'button_style' ) ); ?>">
								<option value="full" <?php selected( NookSL_Settings::get( 'button_style' ), 'full' ); ?>><?php esc_html_e( 'Icon and text', 'nook-social-login' ); ?></option>
								<option value="icons" <?php selected( NookSL_Settings::get( 'button_style' ), 'icons' ); ?>><?php esc_html_e( 'Icons only', 'nook-social-login' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Other', 'nook-social-login' ); ?></th>
						<td>
							<?php
							$this->checkbox( 'use_avatar', __( 'Use the social network avatar', 'nook-social-login' ) );
							$this->checkbox( 'delete_data', __( 'Delete linked accounts data when the plugin is deleted', 'nook-social-login' ) );
							?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p class="nsl-about">
				<?php
				printf(
					/* translators: 1: plugin version, 2: author name, 3: author site link. */
					esc_html__( 'Nook Social Login %1$s · Author: %2$s · Site: %3$s', 'nook-social-login' ),
					esc_html( NOOKSL_VERSION ),
					esc_html__( 'Gorshkov Sergey Vladimirovich', 'nook-social-login' ),
					'<a href="https://nookbat.ru/" target="_blank" rel="noopener">nookbat.ru</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Settings card of one provider.
	 *
	 * @param NookSL_Provider $provider Provider.
	 */
	private function provider_card( NookSL_Provider $provider ) {
		$id      = $provider->id();
		$enabled = $provider->is_enabled();
		if ( $provider->is_active() ) {
			$status       = __( 'Active', 'nook-social-login' );
			$status_class = 'is-active';
		} elseif ( $enabled ) {
			$status       = __( 'Keys are missing', 'nook-social-login' );
			$status_class = 'is-warning';
		} else {
			$status       = __( 'Off', 'nook-social-login' );
			$status_class = 'is-off';
		}
		?>
		<div class="nsl-card" id="nsl-provider-<?php echo esc_attr( $id ); ?>">
			<div class="nsl-card-head">
				<span class="nsl-profile-icon" style="--nsl-color:<?php echo esc_attr( $provider->color() ); ?>"><?php echo NookSL_Icons::svg( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<strong><?php echo esc_html( $provider->label() ); ?></strong>
				<span class="nsl-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span>
			</div>
			<label class="nsl-enable">
				<input type="checkbox" name="<?php echo esc_attr( $this->name( 'providers', $id, 'enabled' ) ); ?>" value="1" <?php checked( $enabled ); ?> />
				<?php esc_html_e( 'Enabled', 'nook-social-login' ); ?>
			</label>
			<p class="description"><?php echo esc_html( $provider->instructions() ); ?></p>
			<p>
				<a href="<?php echo esc_url( $provider->console_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the developer console', 'nook-social-login' ); ?> &#8599;</a>
			</p>
			<?php foreach ( $provider->fields() as $key => $field ) : ?>
				<?php $field_id = 'nsl-' . $id . '-' . $key; ?>
				<p class="nsl-field">
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<?php if ( 'select' === $field['type'] ) : ?>
						<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $this->name( 'providers', $id, $key ) ); ?>">
							<?php foreach ( $field['options'] as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $provider->option( $key ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="<?php echo 'password' === $field['type'] ? 'password' : 'text'; ?>" class="widefat" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $this->name( 'providers', $id, $key ) ); ?>" value="<?php echo esc_attr( $provider->option( $key ) ); ?>" autocomplete="off" spellcheck="false" />
					<?php endif; ?>
					<?php if ( ! empty( $field['description'] ) ) : ?>
						<span class="description"><?php echo esc_html( $field['description'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
			<p class="nsl-field">
				<label for="nsl-<?php echo esc_attr( $id ); ?>-callback"><?php echo esc_html( 'steam' === $id ? __( 'Return URL (informational)', 'nook-social-login' ) : __( 'Redirect URL', 'nook-social-login' ) ); ?></label>
				<span class="nsl-copy">
					<input type="text" class="widefat code" id="nsl-<?php echo esc_attr( $id ); ?>-callback" value="<?php echo esc_attr( $provider->callback_url() ); ?>" readonly />
					<button type="button" class="button nsl-copy-btn" data-target="nsl-<?php echo esc_attr( $id ); ?>-callback"><?php esc_html_e( 'Copy', 'nook-social-login' ); ?></button>
				</span>
			</p>
		</div>
		<?php
	}
}
