<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Customer portal authentication.
 *
 * v6.0.0: PHP sessions were replaced with a signed, expiring, HttpOnly
 * cookie (HMAC-SHA256 over user id + expiry using the per-install WP salt).
 * This is cache-safe, load-balancer friendly and avoids PHP session
 * conflicts. Plaintext passwords are never persisted.
 */
class KeyVault_Manager_User_Auth {
    private static $instance = null;

    const COOKIE_NAME = 'kvm_session';
    const COOKIE_TTL  = 1209600; // 14 days.

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // No PHP sessions: everything is handled through the signed cookie.
        add_action('kvm_daily_maintenance', array('KeyVault_Manager_User_Auth', 'purge_claims'));
    }

    /* ------------------------------------------------------------------
     * Signed cookie auth
     * ---------------------------------------------------------------- */

    private function signature($user_id, $expires) {
        return hash_hmac('sha256', $user_id . '|' . $expires, wp_salt('kvm_portal'));
    }

    private function make_token($user_id) {
        $expires = time() + self::COOKIE_TTL;
        return $user_id . '.' . $expires . '.' . $this->signature($user_id, $expires);
    }

    public function set_auth_cookie($user_id) {
        if (headers_sent()) {
            return false;
        }
        $user_id = intval($user_id);
        setcookie(self::COOKIE_NAME, $this->make_token($user_id), array(
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        $_COOKIE[self::COOKIE_NAME] = $this->make_token($user_id);
        return true;
    }

    public function clear_auth_cookie() {
        if (headers_sent()) {
            return false;
        }
        setcookie(self::COOKIE_NAME, '', array(
            'expires'  => time() - 3600,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        unset($_COOKIE[self::COOKIE_NAME]);
        return true;
    }

    private function cookie_user_id() {
        if (empty($_COOKIE[self::COOKIE_NAME]) || !is_string($_COOKIE[self::COOKIE_NAME])) {
            return 0;
        }
        $parts = explode('.', $_COOKIE[self::COOKIE_NAME]);
        if (count($parts) !== 3) {
            return 0;
        }
        list($uid, $expires, $sig) = $parts;
        if (!ctype_digit($uid) || !ctype_digit($expires)) {
            return 0;
        }
        if (intval($expires) < time()) {
            return 0;
        }
        if (!hash_equals($this->signature($uid, $expires), $sig)) {
            return 0;
        }
        return intval($uid);
    }

    public function get_current_user() {
        static $cached_user = null;
        if ($cached_user !== null) {
            return $cached_user;
        }
        $uid = $this->cookie_user_id();
        if (!$uid) {
            $cached_user = false;
            return false;
        }
        global $wpdb;
        $table = KeyVault_Manager_DB::table('users');
        $cached_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $uid));
        return $cached_user;
    }

    public function logout() {
        $this->clear_auth_cookie();
    }

    /* ------------------------------------------------------------------
     * Atomic one-time claims (fulfillment / webhook idempotency).
     *
     * add_option() is atomic — it fails when the row already exists — so it
     * gives us a race-safe "claim once" guard. Expired claims are purged by
     * the daily maintenance cron.
     * ---------------------------------------------------------------- */

    public static function claim($bucket, $ttl_seconds) {
        $option = '_kvm_claim_' . md5((string) $bucket);
        $expires = time() + intval($ttl_seconds);
        if (add_option($option, $expires, '', 'no')) {
            return true;
        }
        // Already claimed — if the previous claim expired, recycle the slot.
        $stored = get_option($option);
        if ($stored && intval($stored) < time()) {
            delete_option($option);
            return (bool) add_option($option, $expires, '', 'no');
        }
        return false;
    }

    public static function has_claim($bucket) {
        $stored = get_option('_kvm_claim_' . md5((string) $bucket));
        return $stored && intval($stored) >= time();
    }

    public static function purge_claims() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            $wpdb->esc_like('_kvm_claim_') . '%',
            time()
        ));
    }

    /* ------------------------------------------------------------------
     * Rate limiting (transient-backed, per bucket)
     * ---------------------------------------------------------------- */

    public static function throttle($bucket, $max, $window_seconds) {
        $key   = 'kvm_rl_' . md5($bucket);
        $count = intval(get_transient($key));
        if ($count >= $max) {
            return false;
        }
        set_transient($key, $count + 1, intval($window_seconds));
        return true;
    }

    public static function throttle_reset($bucket) {
        delete_transient('kvm_rl_' . md5($bucket));
    }

    /* ------------------------------------------------------------------
     * Passwords — hashes only, plaintext never stored.
     * ---------------------------------------------------------------- */

    public static function generate_temp_password($length = 14) {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $numbers   = '23456789';
        $special   = '!@#$%^&*';

        $pwd = array(
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $special[random_int(0, strlen($special) - 1)],
        );

        $all_chars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $pwd[] = $all_chars[random_int(0, strlen($all_chars) - 1)];
        }

        shuffle($pwd);
        return implode('', $pwd);
    }

    public function set_password($user_id, $plain_password) {
        global $wpdb;
        if (strlen((string) $plain_password) < 6) {
            return false;
        }
        return $wpdb->update(
            KeyVault_Manager_DB::table('users'),
            array('password_hash' => password_hash($plain_password, PASSWORD_BCRYPT)),
            array('id' => intval($user_id))
        );
    }

    public function get_or_create_user($name, $email, $custom_password = '') {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('users');
        $email = sanitize_email($email);
        if (empty($email)) {
            return array('user' => null, 'is_new' => false, 'temp_pass' => '');
        }

        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));
        if ($user) {
            return array('user' => $user, 'is_new' => false, 'temp_pass' => '');
        }

        $temp_pass = !empty($custom_password) ? (string) $custom_password : self::generate_temp_password(14);
        $wpdb->insert($table, array(
            'name'          => sanitize_text_field($name),
            'email'         => $email,
            'password_hash' => password_hash($temp_pass, PASSWORD_BCRYPT),
            'created_at'    => current_time('mysql'),
        ));
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $wpdb->insert_id));
        return array('user' => $user, 'is_new' => true, 'temp_pass' => $temp_pass);
    }

    public function update_user_info($user_id, $name, $email, $new_password = '') {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('users');
        $uid   = intval($user_id);

        $update_data = array(
            'name'  => sanitize_text_field($name),
            'email' => sanitize_email($email),
        );
        $wpdb->update($table, $update_data, array('id' => $uid));

        if (!empty($new_password)) {
            $this->set_password($uid, $new_password);
        }
        return true;
    }

    /**
     * Attempt a portal login. Returns WP_User-like row, false on bad
     * credentials, or WP_Error when rate limited.
     */
    public function authenticate($email, $password) {
        global $wpdb;
        $email = sanitize_email($email);
        $ip    = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';

        if (!self::throttle('login_ip_' . $ip, 10, 10 * MINUTE_IN_SECONDS)) {
            return new WP_Error('kvm_throttled_ip', 'Too many login attempts from this network. Please try again later.');
        }
        if (!empty($email) && !self::throttle('login_email_' . md5(strtolower($email)), 6, 10 * MINUTE_IN_SECONDS)) {
            return new WP_Error('kvm_throttled_email', 'Too many login attempts for this account. Please try again later.');
        }

        if (empty($email) || empty($password)) {
            return false;
        }

        $table = KeyVault_Manager_DB::table('users');
        $user  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));
        if ($user && password_verify((string) $password, $user->password_hash)) {
            self::throttle_reset('login_email_' . md5(strtolower($email)));
            $this->set_auth_cookie($user->id);
            return $user;
        }
        return false;
    }

    public function change_password($user_id, $old_password, $new_password) {
        global $wpdb;
        $table = KeyVault_Manager_DB::table('users');
        $user  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($user_id)));
        if ($user && password_verify((string) $old_password, $user->password_hash)) {
            return (bool) $this->set_password($user_id, $new_password);
        }
        return false;
    }

    /* ------------------------------------------------------------------
     * Password reset tokens (hashed in transients, 1 hour expiry)
     * ---------------------------------------------------------------- */

    public function create_reset_token($user_id) {
        $token = bin2hex(random_bytes(32));
        set_transient(
            'kvm_rtoken_' . hash('sha256', $token),
            intval($user_id) . '|' . (time() + HOUR_IN_SECONDS),
            HOUR_IN_SECONDS
        );
        return $token;
    }

    public function validate_reset_token($token) {
        $value = get_transient('kvm_rtoken_' . hash('sha256', (string) $token));
        if (!$value || strpos($value, '|') === false) {
            return 0;
        }
        list($uid, $expires) = explode('|', $value);
        if (intval($expires) < time()) {
            return 0;
        }
        return intval($uid);
    }

    public function consume_reset_token($token) {
        $uid = $this->validate_reset_token($token);
        if ($uid) {
            delete_transient('kvm_rtoken_' . hash('sha256', (string) $token));
        }
        return $uid;
    }
}
