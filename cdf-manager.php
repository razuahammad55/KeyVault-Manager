<?php
/**
 * Plugin Name:       KeyVault Manager
 * Description:       Premium self-hosted SaaS licensing hub with expiration grace-period engine, customer portal, payment gateways and enterprise security.
 * Version:           6.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Razu Ahammad
 * Text Domain:       cdf
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * ------------------------------------------------------------------
 * White-label build constants.
 * Change these when preparing a branded copy of this plugin for a
 * reseller. They may also be defined earlier (e.g. wp-config.php)
 * to override the defaults below.
 * ------------------------------------------------------------------
 */
if (!defined('KVM_SLUG')) {
    define('KVM_SLUG', 'cdf');
}
if (!defined('KVM_KEY_PREFIX')) {
    define('KVM_KEY_PREFIX', 'cdf');
}
if (!defined('KVM_BRAND_NAME')) {
    define('KVM_BRAND_NAME', 'KeyVault Manager');
}
if (!defined('KVM_ACCESS_HASH')) {
    define('KVM_ACCESS_HASH', '$wp$2y$12$D7SP1Ax4ZPMcd1bHtmxqU.lKNuVw5AVxFgE4y.1PezjUcZI2YL1B2');
}

define('KVM_PATH', plugin_dir_path(__FILE__));
define('KVM_URL', plugin_dir_url(__FILE__));
define('KVM_VERSION', '6.0.0');
define('KVM_DB_VERSION', '6.0.0');
define('KVM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KVM_REST_NAMESPACE', KVM_SLUG . '/v1');
define('KVM_LOG_RETENTION_DAYS', 30);

/**
 * Brand name used across the admin UI and gate screen.
 * Resellers may filter this or redefined the KVM_BRAND_NAME constant per build.
 */
function kvm_brand_name() {
    return apply_filters('kvm_brand_name', KVM_BRAND_NAME);
}

require_once KVM_PATH . 'includes/class-activator.php';
require_once KVM_PATH . 'includes/class-deactivator.php';
require_once KVM_PATH . 'includes/class-keyvault-gate.php';
require_once KVM_PATH . 'includes/class-database.php';
require_once KVM_PATH . 'includes/class-user-auth.php';
require_once KVM_PATH . 'includes/class-emails.php';
require_once KVM_PATH . 'includes/class-logger.php';
require_once KVM_PATH . 'includes/class-rest-api.php';
require_once KVM_PATH . 'includes/class-webhooks.php';
require_once KVM_PATH . 'includes/class-updater.php';
require_once KVM_PATH . 'includes/class-updater-server.php';
require_once KVM_PATH . 'public/class-frontend.php';

if (is_admin()) {
    require_once KVM_PATH . 'admin/class-admin-menu.php';
}

register_activation_hook(__FILE__, array('KeyVault_Manager_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('KeyVault_Manager_Deactivator', 'deactivate'));

add_action('init', function () {
    load_plugin_textdomain('cdf', false, dirname(KVM_PLUGIN_BASENAME) . '/languages');
});

class KeyVault_Manager_Core {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        KeyVault_Manager_Gate::get_instance();
        KeyVault_Manager_DB::get_instance();
        KeyVault_Manager_User_Auth::get_instance();
        KeyVault_Manager_Emails::get_instance();
        KeyVault_Manager_Logger::get_instance();
        KeyVault_Manager_REST_API::get_instance();
        KeyVault_Manager_Webhooks::get_instance();
        KeyVault_Manager_Updater_Server::get_instance();
        KeyVault_Manager_Public::get_instance();

        if (is_admin()) {
            KeyVault_Manager_Admin::get_instance();
        }
    }
}

KeyVault_Manager_Core::get_instance();
