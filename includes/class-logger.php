<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Logger {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('kvm_daily_maintenance', array($this, 'purge_old_logs'));
    }

    public static function log($endpoint, $license_key, $domain, $status_code, $message) {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('logs');

        $wpdb->insert($table, array(
            'license_key' => sanitize_text_field((string) $license_key),
            'endpoint'    => sanitize_text_field((string) $endpoint),
            'domain'      => sanitize_text_field((string) $domain),
            'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0',
            'status_code' => intval($status_code),
            'message'     => sanitize_textarea_field((string) $message),
            'created_at'  => current_time('mysql'),
        ));
    }

    public function get_logs($limit = 50, $offset = 0) {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('logs');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",
            intval($limit),
            intval($offset)
        ));
    }

    public function count_logs() {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('logs');
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
    }

    public function clear_logs() {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('logs');
        return $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Retention: delete logs older than KVM_LOG_RETENTION_DAYS (filterable).
     * Runs daily via the kvm_daily_maintenance cron event.
     */
    public function purge_old_logs() {
        global $wpdb;
        $days = intval(apply_filters('kvm_log_retention_days', KVM_LOG_RETENTION_DAYS));
        if ($days <= 0) {
            return;
        }
        $table = KeyVault_Manager_DB::table('logs');
        $cutoff = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $cutoff));
    }
}
