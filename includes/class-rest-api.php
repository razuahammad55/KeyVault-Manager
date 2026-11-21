<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_REST_API {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : get_option('kvm_rest_namespace', 'kvm/v1');

        register_rest_route($namespace, '/activate', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'api_activate'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route($namespace, '/deactivate', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'api_deactivate'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route($namespace, '/check-license', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'api_check'),
            'permission_callback' => '__return_true'
        ));
    }

    private function normalize_domain($domain) {
        $domain = trim($domain);
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'http://' . $domain;
        }
        $parsed = parse_url($domain, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./i', '', $parsed ? $parsed : trim($domain, '/')));
    }

    public function api_activate($request) {
        global $wpdb;
        $db = KeyVault_Manager_DB::get_instance();
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain = $this->normalize_domain($request->get_param('domain'));

        if (empty($license_key) || empty($domain)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing key or domain.'), 400);
        }

        $lic = $db->find_license($license_key);
        if (!$lic || $lic->status !== 'active') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or revoked license key.'), 403);
        }

        $grace_days = intval(get_option('kvm_grace_period_days', '7'));
        $cutoff_time = current_time('timestamp') - ($grace_days * 86400);

        if (!empty($lic->expires_at) && strtotime($lic->expires_at) < $cutoff_time) {
            return new WP_REST_Response(array('success' => false, 'message' => 'License key has expired.'), 403);
        }

        $table_activations = $wpdb->prefix . $slug . '_activations';
        $already_active = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_activations WHERE license_id = %d AND domain = %s", $lic->id, $domain)));
        $active_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_activations WHERE license_id = %d", $lic->id)));

        if (!$already_active && $active_count >= intval($lic->max_activations)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Activation limit reached.'), 403);
        }

        if (!$already_active) {
            $wpdb->insert($table_activations, array(
                'license_id'          => $lic->id,
                'domain'              => $domain,
                'activated_at'        => current_time('mysql'),
                'last_reassigned_at'  => current_time('mysql')
            ));
        }

        KeyVault_Manager_Logger::log('activate', $license_key, $domain, 200, 'Activation successful.');

        return new WP_REST_Response(array(
            'success'     => true,
            'message'     => 'KeyVault successfully activated.',
            'plan_tier'   => isset($lic->plan_tier) ? $lic->plan_tier : 'Starter',
            'max_sites'   => $lic->max_activations,
            'active_sites'=> $already_active ? $active_count : ($active_count + 1)
        ), 200);
    }

    public function api_deactivate($request) {
        global $wpdb;
        $db = KeyVault_Manager_DB::get_instance();
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain = $this->normalize_domain($request->get_param('domain'));

        if (!empty($license_key) && !empty($domain)) {
            $lic = $db->find_license($license_key);
            if ($lic) {
                $wpdb->delete($wpdb->prefix . $slug . '_activations', array('license_id' => $lic->id, 'domain' => $domain));
            }
        }
        KeyVault_Manager_Logger::log('deactivate', $license_key, $domain, 200, 'Deactivation processed.');
        return new WP_REST_Response(array('success' => true, 'message' => 'KeyVault deactivated.'), 200);
    }

    public function api_check($request) {
        global $wpdb;
        $db = KeyVault_Manager_DB::get_instance();
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $domain = $this->normalize_domain($request->get_param('domain'));

        $lic = $db->find_license($license_key);
        if (!$lic || $lic->status !== 'active') {
            return new WP_REST_Response(array('valid' => false, 'status' => 'revoked'), 200);
        }

        $grace_days = intval(get_option('kvm_grace_period_days', '7'));
        $now = current_time('timestamp');
        $is_expired = (!empty($lic->expires_at) && strtotime($lic->expires_at) < $now);
        $is_in_grace = false;
        $days_remaining_grace = 0;

        if ($is_expired) {
            $grace_cutoff = strtotime($lic->expires_at) + ($grace_days * 86400);
            if ($now <= $grace_cutoff) {
                $is_in_grace = true;
                $days_remaining_grace = max(1, ceil(($grace_cutoff - $now) / 86400));
            } else {
                return new WP_REST_Response(array('valid' => false, 'status' => 'expired'), 200);
            }
        }

        $table_activations = $wpdb->prefix . $slug . '_activations';
        $is_active = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_activations WHERE license_id = %d AND domain = %s", $lic->id, $domain));
        $active_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_activations WHERE license_id = %d", $lic->id)));

        return new WP_REST_Response(array(
            'valid'                => (bool)$is_active,
            'status'               => $is_active ? ($is_in_grace ? 'grace_period' : 'active') : 'unlinked',
            'in_grace_period'      => $is_in_grace,
            'grace_days_remaining' => $days_remaining_grace,
            'plan_tier'            => isset($lic->plan_tier) ? $lic->plan_tier : 'Starter',
            'max_sites'            => $lic->max_activations,
            'active_sites'         => $active_count,
            'expires_at'           => $lic->expires_at
        ), 200);
    }
}