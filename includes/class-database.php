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
        add_action('init', array($this, 'ensure_maintenance_schedule'));
        $this->maybe_upgrade_schema();
    }

    /* ------------------------------------------------------------------
     * Shared helpers — single source of truth for tables and options.
     * ---------------------------------------------------------------- */

    /**
     * Fully-qualified custom table name.
     *
     * @param string $name licenses|activations|users|logs|orders
     * @return string
     */
    public static function table($name) {
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $slug));
        if ($slug === '') {
            $slug = KVM_SLUG;
        }
        return $wpdb->prefix . $slug . '_' . preg_replace('/[^a-z_]/', '', (string) $name);
    }

    /**
     * Read a canonical plugin option (all stored on the kvm_ prefix).
     */
    public static function opt($key, $default = '') {
        return get_option('kvm_' . $key, $default);
    }

    /**
     * Write a canonical plugin option.
     */
    public static function update_opt($key, $value) {
        return update_option('kvm_' . $key, $value);
    }

    /**
     * Copy legacy slug-prefixed options (v5.x) into canonical kvm_* keys once.
     */
    public static function migrate_legacy_options() {
        if (get_option('kvm_legacy_options_migrated')) {
            return;
        }
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $map  = array(
            $slug . '_price_starter'          => 'kvm_price_starter',
            $slug . '_starter_max'            => 'kvm_starter_max',
            $slug . '_price_professional'     => 'kvm_price_professional',
            $slug . '_pro_max'                => 'kvm_pro_max',
            $slug . '_price_enterprise'       => 'kvm_price_enterprise',
            $slug . '_grace_period_days'      => 'kvm_grace_period_days',
            $slug . '_zip_file_path'          => 'kvm_zip_file_path',
            $slug . '_primary_color'          => 'kvm_primary_color',
            $slug . '_custom_login_url'       => 'kvm_custom_login_url',
            $slug . '_delete_data_on_uninstall' => 'kvm_delete_data_on_uninstall',
        );
        foreach ($map as $old => $new) {
            $old_value = get_option($old);
            if ($old_value !== false && get_option($new) === false) {
                update_option($new, $old_value);
            }
        }
        update_option('kvm_legacy_options_migrated', 1);
    }

    /* ------------------------------------------------------------------
     * Schema maintenance — runs once per DB version, not per request.
     * ---------------------------------------------------------------- */

    private function maybe_upgrade_schema() {
        global $wpdb;

        if (get_option('kvm_db_version') === KVM_DB_VERSION) {
            return;
        }

        $table_licenses    = self::table('licenses');
        $table_activations = self::table('activations');

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_licenses));
        if ($exists !== $table_licenses) {
            // Fresh install — the activator creates everything.
            return;
        }

        // Add columns missing from pre-6.0 installs.
        $lic_cols = $this->get_columns($table_licenses);
        if (!isset($lic_cols['parent_id'])) {
            $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `parent_id` BIGINT(20) DEFAULT 0 NOT NULL AFTER `user_id`");
        }
        if (!isset($lic_cols['license_mode'])) {
            $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `license_mode` VARCHAR(20) DEFAULT 'multi' NOT NULL AFTER `max_activations`");
        }
        if (!isset($lic_cols['plan_tier'])) {
            $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `plan_tier` VARCHAR(30) DEFAULT 'Starter' NOT NULL AFTER `license_mode`");
        }
        if (!isset($lic_cols['key_hash'])) {
            $wpdb->query("ALTER TABLE `$table_licenses` ADD COLUMN `key_hash` VARCHAR(64) DEFAULT '' NOT NULL AFTER `license_key`");
        }

        $act_cols = $this->get_columns($table_activations);
        if (!isset($act_cols['last_reassigned_at'])) {
            $wpdb->query("ALTER TABLE `$table_activations` ADD COLUMN `last_reassigned_at` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER `activated_at`");
        }

        // Privacy policy v6.0.0: plaintext passwords are never persisted.
        $user_table = self::table('users');
        $user_cols  = $this->get_columns($user_table);
        if (isset($user_cols['temp_password'])) {
            $wpdb->query("ALTER TABLE `$user_table` DROP COLUMN `temp_password`");
        }

        // Performance indexes (skipped when an equivalent index already exists).
        $this->ensure_index($table_licenses, 'key_hash', 'kvm_key_hash');
        $this->ensure_index($table_licenses, 'user_id', 'kvm_user_id');
        $this->ensure_index($table_licenses, 'status', 'kvm_status');
        $this->ensure_index($table_licenses, 'customer_email', 'kvm_customer_email');
        $orders_table = self::table('orders');
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table)) === $orders_table) {
            $this->ensure_index($orders_table, 'order_id', 'kvm_order_id');
            $this->ensure_index($orders_table, 'customer_email', 'kvm_order_email');
        }

        // Backfill key_hash for legacy rows (batched).
        if (isset($lic_cols['key_hash']) || $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM `' . $table_licenses . '` LIKE %s', 'key_hash'))) {
            $guard = 0;
            while ($guard < 50) {
                $missing = $wpdb->get_results(
                    "SELECT id, license_key FROM `$table_licenses` WHERE key_hash = '' OR key_hash IS NULL LIMIT 500"
                );
                if (empty($missing)) {
                    break;
                }
                foreach ($missing as $row) {
                    $wpdb->update(
                        $table_licenses,
                        array('key_hash' => hash_hmac('sha256', $row->license_key, $this->salt)),
                        array('id' => intval($row->id))
                    );
                }
                $guard++;
            }
        }

        self::migrate_legacy_options();
        update_option('kvm_db_version', KVM_DB_VERSION);
    }

    private function get_columns($table) {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW COLUMNS FROM `$table`");
        $cols = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $cols[$row->Field] = $row;
            }
        }
        return $cols;
    }

    private function ensure_index($table, $column, $index_name) {
        global $wpdb;
        $indexes = $wpdb->get_results("SHOW INDEX FROM `$table`");
        if (empty($indexes)) {
            return;
        }
        $col = strtolower($column);
        foreach ($indexes as $idx) {
            if (intval($idx->Seq_in_index) === 1 && strtolower($idx->Column_name) === $col) {
                return; // Some existing index already serves lookups on this column.
            }
        }
        $name = strtolower($index_name);
        foreach ($indexes as $idx) {
            if (strtolower($idx->Key_name) === $name) {
                return; // Index already present under our name.
            }
        }
        $wpdb->query("ALTER TABLE `$table` ADD INDEX `$index_name` (`$column`)");
    }

    public function ensure_maintenance_schedule() {
        if (!wp_next_scheduled('kvm_daily_maintenance')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'kvm_daily_maintenance');
        }
    }

    /* ------------------------------------------------------------------
     * Keys & licenses
     * ---------------------------------------------------------------- */

    /**
     * Normalize a license key for comparison (uppercase, alphanumerics only).
     */
    public function sanitize_key($key) {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim((string) $key)));
    }

    /**
     * High-entropy license key: PREFIX + 80 bits of randomness (e.g. CDF-A1B2C-D4E5F-60718-293A).
     */
    public function generate_token() {
        $hex     = strtoupper(bin2hex(random_bytes(10)));
        $grouped = implode('-', str_split($hex, 5));
        return strtoupper(KVM_KEY_PREFIX) . '-' . $grouped;
    }

    /**
     * Resolve a license from user input without full-table scans.
     * Tries HMAC candidates first (indexed), then exact key matches.
     */
    public function find_license($license_key) {
        global $wpdb;
        $table = self::table('licenses');

        $raw        = trim((string) $license_key);
        $normalized = $this->sanitize_key($raw);
        if ($raw === '' && $normalized === '') {
            return null;
        }

        $candidates = array_values(array_unique(array($raw, $normalized)));
        $hashes     = array();
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                $hashes[] = hash_hmac('sha256', $candidate, $this->salt);
            }
        }

        if (!empty($hashes)) {
            $placeholders = implode(',', array_fill(0, count($hashes), '%s'));
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE key_hash IN ($placeholders) LIMIT 1",
                $hashes
            ));
            if ($row) {
                return $row;
            }
        }

        // Exact-match fallback (covers rows whose key_hash could not be backfilled).
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s OR license_key = %s LIMIT 1",
            $normalized,
            $raw
        ));
    }

    private function expiry_from_type($type) {
        $now = strtotime(current_time('mysql'));
        if ($type === 'lifetime' || $type === true) {
            return null;
        }
        if ($type === '6_months') {
            return date('Y-m-d H:i:s', strtotime('+6 months', $now));
        }
        if ($type === '3_years') {
            return date('Y-m-d H:i:s', strtotime('+3 years', $now));
        }
        return date('Y-m-d H:i:s', strtotime('+365 days', $now));
    }

    /**
     * Generate license keys for a customer.
     *
     * @param string $customer_email
     * @param string $customer_name
     * @param int    $quantity
     * @param string $license_mode      multi|individual
     * @param mixed  $expiry_type       1_year|6_months|3_years|lifetime|true
     * @param string $notes
     * @param int    $custom_user_id    Bind to an existing portal user instead of creating one.
     * @param string|null $custom_expires_at  Y-m-d date, or null to derive from $expiry_type.
     * @param array  $order_ctx         array(order_id, amount, gateway) used by email templates.
     * @return array Generated raw license keys.
     */
    public function generate_license_keys(
        $customer_email,
        $customer_name,
        $quantity,
        $license_mode = 'multi',
        $expiry_type = '1_year',
        $notes = '',
        $custom_user_id = 0,
        $custom_expires_at = null,
        $order_ctx = array()
    ) {
        global $wpdb;
        $table_licenses = self::table('licenses');
        $table_users    = self::table('users');
        $qty            = max(1, intval($quantity));

        $auth_res = array('user' => null, 'is_new' => false, 'temp_pass' => '');
        if ($custom_user_id > 0) {
            $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_users WHERE id = %d", intval($custom_user_id)));
            if ($user) {
                $customer_email = $user->email;
                $customer_name  = $user->name;
            }
            $auth_res['user'] = $user;
        } else {
            $auth_res = KeyVault_Manager_User_Auth::get_instance()->get_or_create_user($customer_name, $customer_email);
            $user     = $auth_res['user'];
        }

        // Plan tier resolved from the configured slot boundaries.
        $starter_max = intval(self::opt('starter_max', 9));
        $pro_max     = intval(self::opt('pro_max', 99));
        if ($qty > $pro_max) {
            $plan_tier = 'Enterprise';
        } elseif ($qty > $starter_max) {
            $plan_tier = 'Professional';
        } else {
            $plan_tier = 'Starter';
        }

        if ($custom_expires_at !== null) {
            $expires_at = !empty($custom_expires_at)
                ? date('Y-m-d H:i:s', strtotime($custom_expires_at . ' 23:59:59'))
                : null;
        } else {
            $expires_at = $this->expiry_from_type($expiry_type);
        }

        $generated_keys = array();
        $license_mode   = ($license_mode === 'individual') ? 'individual' : 'multi';
        $created_at     = current_time('mysql');

        if ($license_mode === 'individual') {
            $parent_key = $this->generate_token();
            $wpdb->insert($table_licenses, array(
                'user_id'         => $user ? $user->id : 0,
                'parent_id'       => 0,
                'license_key'     => $parent_key,
                'key_hash'        => hash_hmac('sha256', $parent_key, $this->salt),
                'customer_email'  => sanitize_email($customer_email),
                'customer_name'   => sanitize_text_field($customer_name),
                'notes'           => sanitize_textarea_field('Parent Order for ' . $qty . ' Individual Keys'),
                'max_activations' => $qty,
                'license_mode'    => 'individual',
                'plan_tier'       => $plan_tier,
                'status'          => 'parent_holder',
                'expires_at'      => $expires_at,
                'created_at'      => $created_at,
            ));
            $parent_id = $wpdb->insert_id;

            for ($i = 1; $i <= $qty; $i++) {
                $ind_key = $this->generate_token();
                $wpdb->insert($table_licenses, array(
                    'user_id'         => $user ? $user->id : 0,
                    'parent_id'       => $parent_id,
                    'license_key'     => $ind_key,
                    'key_hash'        => hash_hmac('sha256', $ind_key, $this->salt),
                    'customer_email'  => sanitize_email($customer_email),
                    'customer_name'   => sanitize_text_field($customer_name) . ' (Key #' . $i . ')',
                    'notes'           => sanitize_textarea_field($notes),
                    'max_activations' => 1,
                    'license_mode'    => 'individual',
                    'plan_tier'       => $plan_tier,
                    'status'          => 'active',
                    'expires_at'      => $expires_at,
                    'created_at'      => $created_at,
                ));
                $generated_keys[] = $ind_key;
            }
        } else {
            $raw_key = $this->generate_token();
            $wpdb->insert($table_licenses, array(
                'user_id'         => $user ? $user->id : 0,
                'parent_id'       => 0,
                'license_key'     => $raw_key,
                'key_hash'        => hash_hmac('sha256', $raw_key, $this->salt),
                'customer_email'  => sanitize_email($customer_email),
                'customer_name'   => sanitize_text_field($customer_name),
                'notes'           => sanitize_textarea_field($notes),
                'max_activations' => $qty,
                'license_mode'    => 'multi',
                'plan_tier'       => $plan_tier,
                'status'          => 'active',
                'expires_at'      => $expires_at,
                'created_at'      => $created_at,
            ));
            $generated_keys[] = $raw_key;
        }

        KeyVault_Manager_Emails::get_instance()->send_order_emails(
            $customer_name,
            $customer_email,
            $generated_keys,
            $qty,
            $auth_res['temp_pass'],
            empty($expires_at),
            $order_ctx
        );

        return $generated_keys;
    }

    public function update_expiration($license_id, $expiry_type, $custom_date = '') {
        global $wpdb;
        $table = self::table('licenses');
        $license_id = intval($license_id);
        if ($license_id <= 0) {
            return false;
        }

        if ($expiry_type === 'lifetime') {
            $new_expires_at = null;
        } elseif ($expiry_type === 'custom' && !empty($custom_date)) {
            $ts = strtotime($custom_date);
            if ($ts === false) {
                return false;
            }
            $new_expires_at = date('Y-m-d 23:59:59', $ts);
        } elseif ($expiry_type === 'extend_1y') {
            $lic = $wpdb->get_row($wpdb->prepare("SELECT expires_at FROM $table WHERE id = %d", $license_id));
            $now = strtotime(current_time('mysql'));
            $base_time = ($lic && !empty($lic->expires_at) && strtotime($lic->expires_at) > $now)
                ? strtotime($lic->expires_at)
                : $now;
            $new_expires_at = date('Y-m-d H:i:s', strtotime('+1 year', $base_time));
        } else {
            return false;
        }

        return $wpdb->update($table, array('expires_at' => $new_expires_at), array('id' => $license_id));
    }

    public function delete_license_and_activations($license_id) {
        global $wpdb;
        $table_licenses    = self::table('licenses');
        $table_activations = self::table('activations');
        $lic_id = intval($license_id);
        if ($lic_id <= 0) {
            return;
        }

        $wpdb->delete($table_activations, array('license_id' => $lic_id));
        $wpdb->delete($table_licenses, array('parent_id' => $lic_id));
        $wpdb->delete($table_licenses, array('id' => $lic_id));
    }

    public function delete_user_keys_only($user_id) {
        global $wpdb;
        $table = self::table('licenses');
        $user_table = self::table('users');
        $licenses = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d OR customer_email = (SELECT email FROM $user_table WHERE id = %d)",
            intval($user_id),
            intval($user_id)
        ));
        foreach ($licenses as $l) {
            $this->delete_license_and_activations($l->id);
        }
    }

    public function delete_user_and_all_licenses($user_id) {
        global $wpdb;
        $table_users = self::table('users');
        $uid = intval($user_id);
        $this->delete_user_keys_only($uid);
        $wpdb->delete($table_users, array('id' => $uid));
    }

    /* ------------------------------------------------------------------
     * Sequential order IDs, e.g. CDF-JFHV001 ... CDF-JFHV999
     * ---------------------------------------------------------------- */

    public static function generate_order_id() {
        global $wpdb;
        $table  = self::table('orders');
        $prefix = strtoupper(KVM_KEY_PREFIX);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $last = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM $table WHERE order_id LIKE %s ORDER BY id DESC LIMIT 1",
                $wpdb->esc_like($prefix . '-') . '%'
            ));

            $random_block = strtoupper(wp_generate_password(4, false, false));
            $next_seq = 1;

            if ($last && preg_match('/-([A-Z0-9]{4})(\d+)$/', $last, $matches)) {
                if (intval($matches[2]) < 999) {
                    $random_block = $matches[1];
                    $next_seq = intval($matches[2]) + 1;
                }
            }

            $seq_formatted = str_pad((string) $next_seq, 3, '0', STR_PAD_LEFT);
            $candidate = $prefix . '-' . $random_block . $seq_formatted;

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id = %s LIMIT 1", $candidate));
            if (!$exists) {
                return $candidate;
            }
        }

        // Deterministic path failed five times — fall back to pure randomness.
        return $prefix . '-' . strtoupper(wp_generate_password(10, false, false));
    }
}
