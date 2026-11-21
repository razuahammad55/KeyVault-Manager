<?php
/**
 * Plugin Name: KeyVault Manager for CDF
 * Description: Premium Self-Hosted SaaS Licensing Hub with Expiration Grace Period Engine & Enterprise Security.
 * Version: 5.7.6
 * Author: Razu Ahammad
 * Text Domain: cdf
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KVM_SLUG', 'cdf');
define('KVM_KEY_PREFIX', 'cdf');
define('KVM_PATH', plugin_dir_path(__FILE__));
define('KVM_URL', plugin_dir_url(__FILE__));
define('KVM_VERSION', '5.7.6');
define('KVM_ACCESS_HASH', '$wp$2y$12$D7SP1Ax4ZPMcd1bHtmxqU.lKNuVw5AVxFgE4y.1PezjUcZI2YL1B2');
define('KVM_REST_NAMESPACE', 'cdf/v1');

require_once KVM_PATH . 'includes/class-activator.php';
require_once KVM_PATH . 'includes/class-deactivator.php';
require_once KVM_PATH . 'includes/class-keyvault-gate.php';
require_once KVM_PATH . 'includes/class-database.php';
require_once KVM_PATH . 'includes/class-user-auth.php';
require_once KVM_PATH . 'includes/class-emails.php';
require_once KVM_PATH . 'includes/class-logger.php';
require_once KVM_PATH . 'includes/class-rest-api.php';
require_once KVM_PATH . 'includes/class-webhooks.php';
require_once KVM_PATH . 'includes/class-updater-server.php';
require_once KVM_PATH . 'public/class-frontend.php';

if (is_admin()) {
    require_once KVM_PATH . 'admin/class-admin-menu.php';
}

register_activation_hook(__FILE__, array('KeyVault_Manager_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('KeyVault_Manager_Deactivator', 'deactivate'));

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
        KeyVault_Manager_Public::get_instance(); // Aligned with public/class-frontend.php

        if (is_admin()) {
            KeyVault_Manager_Admin::get_instance(); // Aligned with admin/class-admin-menu.php
        }
    }
}

KeyVault_Manager_Core::get_instance();