<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_licenses    = KeyVault_Manager_DB::table('licenses');
        $table_activations = KeyVault_Manager_DB::table('activations');
        $table_logs        = KeyVault_Manager_DB::table('logs');
        $table_users       = KeyVault_Manager_DB::table('users');
        $table_orders      = KeyVault_Manager_DB::table('orders');

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE $table_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            password_hash varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) $charset_collate;");

        dbDelta("CREATE TABLE $table_licenses (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0 NOT NULL,
            parent_id bigint(20) DEFAULT 0 NOT NULL,
            license_key varchar(128) NOT NULL,
            key_hash varchar(64) DEFAULT '' NOT NULL,
            customer_email varchar(100) DEFAULT '' NOT NULL,
            customer_name varchar(100) DEFAULT '' NOT NULL,
            notes text NOT NULL,
            max_activations int(11) DEFAULT 1 NOT NULL,
            license_mode varchar(20) DEFAULT 'multi' NOT NULL,
            plan_tier varchar(30) DEFAULT 'Starter' NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY key_hash (key_hash),
            KEY user_id (user_id),
            KEY status (status),
            KEY customer_email (customer_email)
        ) $charset_collate;");

        dbDelta("CREATE TABLE $table_activations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_id bigint(20) NOT NULL,
            domain varchar(255) NOT NULL,
            activated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_reassigned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY license_domain (license_id, domain)
        ) $charset_collate;");

        dbDelta("CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_key varchar(128) DEFAULT '' NOT NULL,
            endpoint varchar(50) NOT NULL,
            domain varchar(255) DEFAULT '' NOT NULL,
            ip_address varchar(45) NOT NULL,
            status_code int(11) NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;");

        dbDelta("CREATE TABLE $table_orders (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) DEFAULT '' NOT NULL,
            customer_name varchar(100) DEFAULT '' NOT NULL,
            customer_email varchar(100) DEFAULT '' NOT NULL,
            amount decimal(10,2) DEFAULT '0.00' NOT NULL,
            gateway varchar(50) DEFAULT 'Stripe' NOT NULL,
            quantity int(11) DEFAULT 1 NOT NULL,
            license_mode varchar(20) DEFAULT 'multi' NOT NULL,
            license_keys text NOT NULL,
            status varchar(20) DEFAULT 'completed' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            KEY customer_email (customer_email)
        ) $charset_collate;");

        // Canonical option defaults (all plugin options live on the kvm_ prefix).
        if (!get_option('kvm_secret_salt')) {
            update_option('kvm_secret_salt', bin2hex(random_bytes(16)));
        }
        if (get_option('kvm_primary_color') === false) {
            update_option('kvm_primary_color', '#2563eb');
        }
        if (get_option('kvm_grace_period_days') === false) {
            update_option('kvm_grace_period_days', '7');
        }
        if (get_option('kvm_price_starter') === false) {
            update_option('kvm_price_starter', '49');
        }
        if (get_option('kvm_price_professional') === false) {
            update_option('kvm_price_professional', '39');
        }
        if (get_option('kvm_price_enterprise') === false) {
            update_option('kvm_price_enterprise', '30');
        }
        if (get_option('kvm_starter_max') === false) {
            update_option('kvm_starter_max', '9');
        }
        if (get_option('kvm_pro_max') === false) {
            update_option('kvm_pro_max', '99');
        }
        if (get_option('kvm_environment') === false) {
            update_option('kvm_environment', 'sandbox');
        }
        if (get_option('kvm_enable_nonce') === false) {
            update_option('kvm_enable_nonce', 1);
        }
        if (get_option('kvm_delete_data_on_uninstall') === false) {
            update_option('kvm_delete_data_on_uninstall', 0);
        }
        if (get_option('kvm_client_plugin_slug') === false) {
            update_option('kvm_client_plugin_slug', 'client-plugin');
        }
        if (get_option('kvm_client_version') === false) {
            update_option('kvm_client_version', '1.0.0');
        }

        // Legacy option migration (v5.x stored some settings under slug-prefixed keys).
        KeyVault_Manager_DB::migrate_legacy_options();

        // Mark schema as current so the upgrade routine does not re-run.
        update_option('kvm_db_version', KVM_DB_VERSION);

        // Daily maintenance cron (log retention + renewal reminders).
        if (!wp_next_scheduled('kvm_daily_maintenance')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'kvm_daily_maintenance');
        }

        self::create_account_page();
    }

    public static function create_account_page() {
        $slug      = KVM_SLUG;
        $page_slug = $slug . '-account';
        $page_title = trim(kvm_brand_name() . ' Account');

        global $wpdb;
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1",
            $page_slug
        ));

        if (!$page_id) {
            wp_insert_post(array(
                'post_title'     => $page_title,
                'post_name'      => $page_slug,
                'post_content'   => '[' . $slug . '_client_dashboard]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ));
            flush_rewrite_rules();
        }
    }
}
