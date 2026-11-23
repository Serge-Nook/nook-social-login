<?php
/**
 * Plugin Name:       Nook Social Login
 * Plugin URI:        https://nookbat.ru/
 * Description:       Registration and login via VK ID, Steam, Battle.net, Yandex ID, Google, Twitch and Discord.
 * Version:           0.1.1
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Горшков Сергей Владимирович
 * Author URI:        https://nookbat.ru/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nook-social-login
 * Domain Path:       /languages
 *
 * @package NookSocialLogin
 */

defined( 'ABSPATH' ) || exit;

define( 'NOOKSL_VERSION', '0.1.1' );
define( 'NOOKSL_FILE', __FILE__ );
define( 'NOOKSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOOKSL_URL', plugin_dir_url( __FILE__ ) );

require_once NOOKSL_DIR . 'includes/class-nsl-settings.php';
require_once NOOKSL_DIR . 'includes/class-nsl-icons.php';
require_once NOOKSL_DIR . 'includes/class-nsl-provider.php';
require_once NOOKSL_DIR . 'includes/class-nsl-oauth2-provider.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-vk.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-steam.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-battlenet.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-yandex.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-google.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-twitch.php';
require_once NOOKSL_DIR . 'includes/providers/class-nsl-provider-discord.php';
require_once NOOKSL_DIR . 'includes/class-nsl-users.php';
require_once NOOKSL_DIR . 'includes/class-nsl-auth.php';
require_once NOOKSL_DIR . 'includes/class-nsl-buttons.php';
require_once NOOKSL_DIR . 'includes/class-nsl-profile.php';
require_once NOOKSL_DIR . 'includes/class-nsl-admin.php';
require_once NOOKSL_DIR . 'includes/class-nsl-plugin.php';

NookSL_Plugin::instance();
