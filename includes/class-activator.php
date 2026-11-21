<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Activator {
    public static function activate() {
        ob_start();
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $slug = KVM_SLUG;
        $table_licenses = $wpdb->prefix . $slug . '_licenses';
        $table_activations = $wpdb->prefix . $slug . '_activations';
        $table_logs = $wpdb->prefix . $slug . '_logs';
        $table_users = $wpdb->prefix . $slug . '_users';
        $table_orders = $wpdb->prefix . $slug . '_orders';

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta("CREATE TABLE $table_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            password_hash varchar(255) NOT NULL,
            temp_password varchar(100) DEFAULT '' NOT NULL,
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
            PRIMARY KEY  (id)
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
            PRIMARY KEY  (id)
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
            PRIMARY KEY  (id)
        ) $charset_collate;");

        if (!get_option('kvm_secret_salt')) {
            update_option('kvm_secret_salt', bin2hex(random_bytes(16)));
        }
        if (!get_option('kvm_primary_color')) {
            update_option('kvm_primary_color', '#2563eb');
        }
        if (!get_option('kvm_grace_period_days')) update_option('kvm_grace_period_days', '7');
        if (!get_option('kvm_price_starter')) update_option('kvm_price_starter', '49');
        if (!get_option('kvm_price_professional')) update_option('kvm_price_professional', '39');
        if (!get_option('kvm_price_enterprise')) update_option('kvm_price_enterprise', '30');

        // Automatically create the customer account page
        self::create_account_page();
    }
    
    public static function create_account_page() {
        $slug = KVM_SLUG;
        $page_slug = $slug . '-account';
        $page_title = ucwords($slug) . ' Account';

        global $wpdb;
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page'",
            $page_slug
        ));

        if (!$page_id) {
            wp_insert_post(array(
                'post_title'    => $page_title,
                'post_name'     => $page_slug,
                'post_content'  => '[' . $slug . '_client_dashboard]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'comment_status' => 'closed',
                'ping_status'   => 'closed',
            ));
        }
    }
}