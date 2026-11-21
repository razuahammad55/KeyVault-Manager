<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Deactivator {
    public static function deactivate() {
        // Clear scheduled maintenance (log retention + renewal reminders).
        $timestamp = wp_next_scheduled('kvm_daily_maintenance');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'kvm_daily_maintenance');
        }
        wp_clear_scheduled_hook('kvm_daily_maintenance');
    }
}
