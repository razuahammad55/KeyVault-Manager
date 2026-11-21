<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Logger {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }
    private function __construct() {}

    public static function log($endpoint, $license_key, $domain, $status_code, $message) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_logs = $wpdb->prefix . $slug . '_logs';

        $wpdb->insert($table_logs, array(
            'license_key' => sanitize_text_field($license_key),
            'endpoint'    => sanitize_text_field($endpoint),
            'domain'      => sanitize_text_field($domain),
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'status_code' => intval($status_code),
            'message'     => sanitize_textarea_field($message)
        ));
    }

    public function get_logs($limit = 100) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_logs = $wpdb->prefix . $slug . '_logs';

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_logs ORDER BY id DESC LIMIT %d", intval($limit)));
    }

    public function clear_logs() {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_logs = $wpdb->prefix . $slug . '_logs';

        return $wpdb->query("TRUNCATE TABLE $table_logs");
    }
}