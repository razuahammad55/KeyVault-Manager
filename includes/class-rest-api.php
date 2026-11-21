<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Public licensing REST API.
 *
 * Endpoints (namespace cdf/v1): /activate, /deactivate, /check-license.
 * Response shapes are unchanged from v5.x for client-product compatibility.
 */
class KeyVault_Manager_REST_API {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1';

        register_rest_route($namespace, '/activate', array(
            'methods'             => array('GET', 'POST'),
            'callback'            => array($this, 'api_activate'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/deactivate', array(
            'methods'             => array('GET', 'POST'),
            'callback'            => array($this, 'api_deactivate'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/check-license', array(
            'methods'             => array('GET', 'POST'),
            'callback'            => array($this, 'api_check'),
            'permission_callback' => '__return_true',
        ));
    }

    private function normalize_domain($domain) {
        $domain = trim((string) $domain);
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'http://' . $domain;
        }
        $parsed = parse_url($domain, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./i', '', $parsed ? $parsed : trim($domain, '/')));
    }

    private function client_ip() {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
    }

    /**
     * Transient-backed per-IP throttle. Returns a 429 response or null when allowed.
     */
    private function guard_rate_limit() {
        $ip = $this->client_ip();
        if (!KeyVault_Manager_User_Auth::throttle('rest_' . $ip, 120, 5 * MINUTE_IN_SECONDS)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ), 429);
        }
        return null;
    }

    private function license_is_expired($lic) {
        if (empty($lic->expires_at)) {
            return false;
        }
        $grace_days = intval(get_option('kvm_grace_period_days', '7'));
        $cutoff     = strtotime($lic->expires_at) - ($grace_days * DAY_IN_SECONDS);
        $now        = strtotime(current_time('mysql'));
        return $now > $cutoff;
    }

    private function send_invalid_attempt_notice($license_key, $domain) {
        // Throttle these admin alerts to 5/hour per IP to prevent mail floods.
        $bucket = 'invalid_notice_' . md5($this->client_ip());
        if (!KeyVault_Manager_User_Auth::throttle($bucket, 5, HOUR_IN_SECONDS)) {
            return;
        }
        KeyVault_Manager_Emails::get_instance()->send_event_email('admin_invalid_attempt', '', array(
            'domain'       => $domain,
            'license_keys' => $license_key,
            'ip_address'   => $this->client_ip(),
        ));
    }

    public function api_activate($request) {
        $guard = $this->guard_rate_limit();
        if ($guard) {
            return $guard;
        }

        global $wpdb;
        $db          = KeyVault_Manager_DB::get_instance();
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain      = $this->normalize_domain($request->get_param('domain'));

        if (empty($license_key) || empty($domain)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing key or domain.'), 400);
        }

        $lic = $db->find_license($license_key);
        if (!$lic || $lic->status !== 'active') {
            KeyVault_Manager_Logger::log('activate', $license_key, $domain, 403, 'Invalid or revoked license key.');
            $this->send_invalid_attempt_notice($license_key, $domain);
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or revoked license key.'), 403);
        }

        if ($this->license_is_expired($lic)) {
            KeyVault_Manager_Logger::log('activate', $license_key, $domain, 403, 'License key has expired.');
            $this->send_invalid_attempt_notice($license_key, $domain);
            return new WP_REST_Response(array('success' => false, 'message' => 'License key has expired.'), 403);
        }

        $table_activations = KeyVault_Manager_DB::table('activations');
        $already_active    = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_activations WHERE license_id = %d AND domain = %s",
            $lic->id,
            $domain
        )));
        $active_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_activations WHERE license_id = %d",
            $lic->id
        )));

        if (!$already_active && $active_count >= intval($lic->max_activations)) {
            KeyVault_Manager_Logger::log('activate', $license_key, $domain, 403, 'Activation limit reached.');
            return new WP_REST_Response(array('success' => false, 'message' => 'Activation limit reached.'), 403);
        }

        if (!$already_active) {
            $now = current_time('mysql');
            $wpdb->insert($table_activations, array(
                'license_id'         => $lic->id,
                'domain'             => $domain,
                'activated_at'       => $now,
                'last_reassigned_at' => $now,
            ));

            KeyVault_Manager_Logger::log('activate', $license_key, $domain, 200, 'Activation successful.');

            $email_data = array(
                'domain'        => $domain,
                'license_keys'  => $lic->license_key,
                'customer_name' => $lic->customer_name,
                'customer_email' => $lic->customer_email,
            );
            KeyVault_Manager_Emails::get_instance()->send_event_email('admin_site_activated', '', $email_data);
            KeyVault_Manager_Emails::get_instance()->send_event_email('user_domain_updated', $lic->customer_email, $email_data);
        }

        return new WP_REST_Response(array(
            'success'      => true,
            'message'      => 'KeyVault successfully activated.',
            'plan_tier'    => isset($lic->plan_tier) ? $lic->plan_tier : 'Starter',
            'max_sites'    => $lic->max_activations,
            'active_sites' => $already_active ? $active_count : ($active_count + 1),
        ), 200);
    }

    public function api_deactivate($request) {
        $guard = $this->guard_rate_limit();
        if ($guard) {
            return $guard;
        }

        global $wpdb;
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain      = $this->normalize_domain($request->get_param('domain'));

        if (!empty($license_key) && !empty($domain)) {
            $lic  = KeyVault_Manager_DB::get_instance()->find_license($license_key);
            if ($lic) {
                $deleted = $wpdb->delete(KeyVault_Manager_DB::table('activations'), array(
                    'license_id' => $lic->id,
                    'domain'     => $domain,
                ));
                if ($deleted) {
                    $email_data = array(
                        'domain'        => $domain,
                        'license_keys'  => $lic->license_key,
                        'customer_name' => $lic->customer_name,
                        'customer_email' => $lic->customer_email,
                    );
                    KeyVault_Manager_Emails::get_instance()->send_event_email('admin_site_unlinked', '', $email_data);
                    KeyVault_Manager_Emails::get_instance()->send_event_email('user_domain_updated', $lic->customer_email, $email_data);
                }
            }
        }
        KeyVault_Manager_Logger::log('deactivate', $license_key, $domain, 200, 'Deactivation processed.');
        return new WP_REST_Response(array('success' => true, 'message' => 'KeyVault deactivated.'), 200);
    }

    public function api_check($request) {
        $guard = $this->guard_rate_limit();
        if ($guard) {
            return $guard;
        }

        global $wpdb;
        $db          = KeyVault_Manager_DB::get_instance();
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain      = $this->normalize_domain($request->get_param('domain'));

        $lic = $db->find_license($license_key);
        if (!$lic || $lic->status !== 'active') {
            return new WP_REST_Response(array('valid' => false, 'status' => 'revoked'), 200);
        }

        $grace_days = intval(get_option('kvm_grace_period_days', '7'));
        $now        = strtotime(current_time('mysql'));
        $is_expired = (!empty($lic->expires_at) && strtotime($lic->expires_at) < $now);
        $is_in_grace = false;
        $days_remaining_grace = 0;

        if ($is_expired) {
            $grace_cutoff = strtotime($lic->expires_at) + ($grace_days * DAY_IN_SECONDS);
            if ($now <= $grace_cutoff) {
                $is_in_grace = true;
                $days_remaining_grace = max(1, ceil(($grace_cutoff - $now) / DAY_IN_SECONDS));
            } else {
                return new WP_REST_Response(array('valid' => false, 'status' => 'expired'), 200);
            }
        }

        $table_activations = KeyVault_Manager_DB::table('activations');
        $is_active = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_activations WHERE license_id = %d AND domain = %s",
            $lic->id,
            $domain
        ));
        $active_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_activations WHERE license_id = %d",
            $lic->id
        )));

        return new WP_REST_Response(array(
            'valid'                => (bool) $is_active,
            'status'               => $is_active ? ($is_in_grace ? 'grace_period' : 'active') : 'unlinked',
            'in_grace_period'      => $is_in_grace,
            'grace_days_remaining' => $days_remaining_grace,
            'plan_tier'            => isset($lic->plan_tier) ? $lic->plan_tier : 'Starter',
            'max_sites'            => $lic->max_activations,
            'active_sites'         => $active_count,
            'expires_at'           => $lic->expires_at,
        ), 200);
    }
}
