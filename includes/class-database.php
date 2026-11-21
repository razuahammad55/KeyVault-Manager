<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_DB {
    private static $instance = null;
    private $salt;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->salt = get_option('kvm_secret_salt', 'keyvault_fallback_salt_key');
        $this->maybe_upgrade_schema();
    }

    private function maybe_upgrade_schema() {
    global $wpdb;
    $slug = get_option('kvm_plugin_slug', KVM_SLUG);
    $table_licenses = $wpdb->prefix . $slug . '_licenses';
    $table_activations = $wpdb->prefix . $slug . '_activations';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_licenses}'") !== $table_licenses) {
        return;
    }

    if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_licenses` LIKE 'parent_id'")) {
        $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `parent_id` BIGINT(20) DEFAULT 0 NOT NULL AFTER `user_id`");
    }
    if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_licenses` LIKE 'license_mode'")) {
        $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `license_mode` VARCHAR(20) DEFAULT 'multi' NOT NULL AFTER `max_activations`");
    }
    if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_licenses` LIKE 'plan_tier'")) {
        $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `plan_tier` VARCHAR(30) DEFAULT 'Starter' NOT NULL AFTER `license_mode`");
    }
    if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_activations` LIKE 'last_reassigned_at'")) {
        $wpdb->query("ALTER TABLE `$table_activations` ADD COLUMN `last_reassigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER `activated_at`");
    }
}

    public function sanitize_key($key) {
        return strtoupper(str_replace(array('-', ' ', '_'), '', trim($key)));
    }

    public function generate_token() {
        $hub_url = site_url();
        $encoded_url = str_replace(array('=', '+', '/', '-', '_'), '', base64_encode($hub_url));
        
        $leading_hex  = strtoupper(bin2hex(random_bytes(2)));
        $trailing_hex = strtoupper(bin2hex(random_bytes(2)));
        
		return strtoupper(KVM_KEY_PREFIX) . $leading_hex . $encoded_url . $trailing_hex;
    }

    public function find_license($license_key) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';

        $clean_input = $this->sanitize_key($license_key);
        $all_licenses = $wpdb->get_results("SELECT * FROM $table_licenses");

        foreach ($all_licenses as $lic) {
            $clean_stored = $this->sanitize_key($lic->license_key);
            if ($clean_input === $clean_stored) {
                return $lic;
            }
        }

        $key_hash = hash_hmac('sha256', trim($license_key), $this->salt);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_licenses WHERE key_hash = %s", $key_hash));
    }

    public function generate_license_keys($customer_email, $customer_name, $quantity, $license_mode = 'multi', $expiry_type = '1_year', $notes = '', $custom_user_id = 0, $custom_expires_at = null) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';
        $table_users = $wpdb->prefix . $slug . '_users';

        if ($custom_user_id > 0) {
            $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE id = %d", intval($custom_user_id)));
            if ($user) {
                $customer_email = $user->email;
                $customer_name = $user->name;
            }
            $auth_res = array('user' => $user, 'is_new' => false, 'temp_pass' => '');
        } else {
            $auth_res = KeyVault_Manager_User_Auth::get_instance()->get_or_create_user($customer_name, $customer_email);
            $user = $auth_res['user'];
        }

        $qty = intval($quantity);
        $plan_tier = ($qty >= 100) ? 'Enterprise' : (($qty >= 10) ? 'Professional' : 'Starter');
        
        if ($custom_expires_at !== null) {
            $expires_at = !empty($custom_expires_at) ? date('Y-m-d H:i:s', strtotime($custom_expires_at . ' 23:59:59')) : null;
        } else {
            if ($expiry_type === 'lifetime' || $expiry_type === true) {
                $expires_at = null;
            } elseif ($expiry_type === '6_months') {
                $expires_at = date('Y-m-d H:i:s', strtotime('+6 months'));
            } elseif ($expiry_type === '3_years') {
                $expires_at = date('Y-m-d H:i:s', strtotime('+3 years'));
            } else {
                $expires_at = date('Y-m-d H:i:s', strtotime('+365 days'));
            }
        }

        $generated_keys = array();

        if ($license_mode === 'individual') {
            $parent_key = $this->generate_token();
            $wpdb->insert($table_licenses, array(
                'user_id'          => $user ? $user->id : 0,
                'parent_id'        => 0,
                'license_key'      => $parent_key,
                'key_hash'         => hash_hmac('sha256', $parent_key, $this->salt),
                'customer_email'   => sanitize_email($customer_email),
                'customer_name'    => sanitize_text_field($customer_name),
                'notes'            => 'Parent Order for ' . $qty . ' Individual Keys',
                'max_activations'  => $qty,
                'license_mode'     => 'individual',
                'plan_tier'        => $plan_tier,
                'status'           => 'parent_holder',
                'expires_at'       => $expires_at,
                'created_at'       => current_time('mysql')
            ));
            $parent_id = $wpdb->insert_id;

            for ($i = 1; $i <= $qty; $i++) {
                $ind_key = $this->generate_token();
                $wpdb->insert($table_licenses, array(
                    'user_id'          => $user ? $user->id : 0,
                    'parent_id'        => $parent_id,
                    'license_key'      => $ind_key,
                    'key_hash'         => hash_hmac('sha256', $ind_key, $this->salt),
                    'customer_email'   => sanitize_email($customer_email),
                    'customer_name'    => sanitize_text_field($customer_name) . " (Key #{$i})",
                    'notes'            => sanitize_textarea_field($notes),
                    'max_activations'  => 1,
                    'license_mode'     => 'individual',
                    'plan_tier'        => $plan_tier,
                    'status'           => 'active',
                    'expires_at'       => $expires_at,
                    'created_at'       => current_time('mysql')
                ));
                $generated_keys[] = $ind_key;
            }
        } else {
            $raw_key = $this->generate_token();
            $wpdb->insert($table_licenses, array(
                'user_id'          => $user ? $user->id : 0,
                'parent_id'        => 0,
                'license_key'      => $raw_key,
                'key_hash'         => hash_hmac('sha256', $raw_key, $this->salt),
                'customer_email'   => sanitize_email($customer_email),
                'customer_name'    => sanitize_text_field($customer_name),
                'notes'            => sanitize_textarea_field($notes),
                'max_activations'  => $qty,
                'license_mode'     => 'multi',
                'plan_tier'        => $plan_tier,
                'status'           => 'active',
                'expires_at'       => $expires_at,
                'created_at'       => current_time('mysql')
            ));
            $generated_keys[] = $raw_key;
        }

        KeyVault_Manager_Emails::get_instance()->send_order_emails($customer_name, $customer_email, $generated_keys[0], $qty, $auth_res['temp_pass'], empty($expires_at));
        return $generated_keys;
    }

    public function update_expiration($license_id, $expiry_type, $custom_date = '') {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';

        if ($expiry_type === 'lifetime') {
            $new_expires_at = null;
        } elseif ($expiry_type === 'custom' && !empty($custom_date)) {
            $new_expires_at = date('Y-m-d H:i:s', strtotime($custom_date . ' 23:59:59'));
        } elseif ($expiry_type === 'extend_1y') {
            $lic = $wpdb->get_row($wpdb->prepare("SELECT expires_at FROM $table_licenses WHERE id = %d", intval($license_id)));
            $base_time = ($lic && !empty($lic->expires_at) && strtotime($lic->expires_at) > current_time('timestamp')) ? strtotime($lic->expires_at) : current_time('timestamp');
            $new_expires_at = date('Y-m-d H:i:s', strtotime('+1 year', $base_time));
        } else {
            return false;
        }

        return $wpdb->update(
            $table_licenses,
            array('expires_at' => $new_expires_at),
            array('id' => intval($license_id))
        );
    }

    public function delete_license_and_activations($license_id) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';
        $table_activations = $wpdb->prefix . $slug . '_activations';
        $lic_id = intval($license_id);
        
        $wpdb->delete($table_activations, array('license_id' => $lic_id));
        $wpdb->delete($table_licenses, array('parent_id' => $lic_id));
        $wpdb->delete($table_licenses, array('id' => $lic_id));
    }

    public function delete_user_keys_only($user_id) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';
        $licenses = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table_licenses WHERE user_id = %d", intval($user_id)));
        foreach ($licenses as $l) {
            $this->delete_license_and_activations($l->id);
        }
    }

    public function delete_user_and_all_licenses($user_id) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_users = $wpdb->prefix . $slug . '_users';
        $uid = intval($user_id);
        $this->delete_user_keys_only($uid);
        $wpdb->delete($table_users, array('id' => $uid));
    }
}