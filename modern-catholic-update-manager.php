<?php
/**
 * Plugin Name: Modern Catholic – Update Manager
 * Plugin URI: https://github.com/twitchd8/modern-catholic-plugin-update-manager
 * Description: Discovers and installs trusted Modern Catholic releases from GitHub through WordPress's native update system.
 * Version: 1.0.2
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Andrew T. Schmitt
 * License: GPL-3.0-only
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI: https://github.com/twitchd8/modern-catholic-plugin-update-manager
 * Text Domain: modern-catholic-plugin-update-manager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MODERN_CATHOLIC_UPDATE_MANAGER_VERSION', '1.0.2' );
define( 'MODERN_CATHOLIC_UPDATE_MANAGER_FILE', __FILE__ );
define( 'MODERN_CATHOLIC_UPDATE_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'MODERN_CATHOLIC_UPDATE_MANAGER_URL', plugin_dir_url( __FILE__ ) );

require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-repository-registry.php';
require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-credential-file.php';
require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-github-client.php';
require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-update-manager.php';
require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-admin-page.php';
require_once MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::instance()->boot();
