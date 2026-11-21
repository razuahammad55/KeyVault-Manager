<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * Full data wipe (v6.0.0).
 *
 * When "Wipe Data on Uninstall" is enabled in Settings (kvm_delete_data_on_uninstall),
 * deleting the plugin removes every trace:
 *   - all five custom tables (licenses, activations, users, orders, logs)
 *   - every plugin option, including stored gateway credentials
 *   - all transients (fulfillment dedupe, rate limits, reset tokens, cron flags)
 *   - the auto-created customer portal page
 *   - scheduled maintenance events
 *
 * When the option is disabled, all data is preserved (customer choice).
 */

$wipe = get_option('kvm_delete_data_on_uninstall', 0);
if (intval($wipe) !== 1) {
    return; // Data preservation was selected — do nothing.
}

// Resolve the table slug exactly like the plugin does (uninstall.php runs
// without the plugin loaded, so KVM_SLUG is not defined here).
$slug = get_option('kvm_plugin_slug', 'cdf');
$slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $slug));
if ($slug === '') {
    $slug = 'cdf';
}

/* 1. Drop custom tables. */
foreach (array('licenses', 'activations', 'users', 'orders', 'logs') as $table_suffix) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$slug}_{$table_suffix}");
}

/* 2. Delete all plugin options (kvm_* namespace is exclusive to this plugin). */
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like('kvm_') . "%'"
);

/* 3. Delete legacy slug-prefixed options from v5.x (explicit list only). */
$legacy_option_names = array(
    $slug . '_price_starter',
    $slug . '_starter_max',
    $slug . '_price_professional',
    $slug . '_pro_max',
    $slug . '_price_enterprise',
    $slug . '_grace_period_days',
    $slug . '_zip_file_path',
    $slug . '_primary_color',
    $slug . '_custom_login_url',
    $slug . '_delete_data_on_uninstall',
);
foreach ($legacy_option_names as $option_name) {
    delete_option($option_name);
}

/* 4. Delete transients and their timeouts (ours live on kvm_ and {slug}_ prefixes). */
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '" . $wpdb->esc_like('_transient_kvm_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_transient_timeout_kvm_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_transient_' . $slug . '_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_transient_timeout_' . $slug . '_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_site_transient_kvm_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_site_transient_timeout_kvm_') . "%'
        OR option_name LIKE '" . $wpdb->esc_like('_kvm_claim_') . "%'"
);

/* 5. Clear scheduled events. */
wp_clear_scheduled_hook('kvm_daily_maintenance');

/* 6. Remove the auto-created customer portal page. */
$page = get_page_by_path($slug . '-account');
if ($page && $page->post_type === 'page') {
    wp_delete_post($page->ID, true);
}
