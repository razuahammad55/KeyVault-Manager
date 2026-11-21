<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Webhooks {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_routes'));
    }

    public function register_webhook_routes() {
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : get_option('kvm_rest_namespace', 'kvm/v1');

        register_rest_route($namespace, '/webhook/stripe', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_stripe_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    public function handle_stripe_webhook($request) {
        $data = json_decode($request->get_body(), true);
        if (empty($data['type']) || $data['type'] !== 'checkout.session.completed') {
            return new WP_REST_Response(array('received' => true), 200);
        }
        $session = $data['data']['object'];
        $db = KeyVault_Manager_DB::get_instance();
        $mode = $session['metadata']['license_mode'] ?? 'multi';
        $qty = intval($session['metadata']['quantity'] ?? 1);
        $keys = $db->generate_license_keys($session['customer_details']['email'] ?? '', $session['customer_details']['name'] ?? '', $qty, $mode, ($qty >= 100), 'Stripe Order');
        return new WP_REST_Response(array('success' => true, 'keys' => $keys), 200);
    }
}