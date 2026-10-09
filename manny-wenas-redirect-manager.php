<?php
/**
 * Plugin Name:       Manny Wenas Redirect Manager
 * Description:       301/302 redirect manager with wildcard redirects, redirect chain detection and a 404 monitor.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Manfred Wenas
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       manny-wenas-redirect-manager
 * Domain Path:       /languages
 *
 * @package MannyWenasRedirectManager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MWRM_VERSION', '1.0.0');
define('MWRM_FILE', __FILE__);
define('MWRM_PATH', plugin_dir_path(__FILE__));
define('MWRM_URL', plugin_dir_url(__FILE__));

require_once MWRM_PATH . 'includes/class-mwrm-installer.php';
require_once MWRM_PATH . 'includes/class-mwrm-integration.php';
require_once MWRM_PATH . 'includes/class-mwrm-slug-watcher.php';
require_once MWRM_PATH . 'includes/class-mwrm-plugin.php';
require_once MWRM_PATH . 'admin/class-mwrm-admin.php';

register_activation_hook(__FILE__, array('MWRM_Installer', 'activate'));

add_action('plugins_loaded', array('MWRM_Plugin', 'init'));
