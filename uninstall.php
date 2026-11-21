<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user enabled deletion on uninstall
if (get_option('kvm_delete_data_on_uninstall', 0) == 1) {
    global $wpdb;
    
    // Define your slug constant or fetch it if defined
    $slug = defined('KVM_SLUG') ? KVM_SLUG : 'cdf';

    // Drop custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$slug}_licenses");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$slug}_activations");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$slug}_users");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$slug}_logs");

    // Delete options
    delete_option('kvm_plugin_slug');
    delete_option('kvm_access_hash');
    delete_option('kvm_plugin_unlocked');
    delete_option('kvm_delete_data_on_uninstall');
}