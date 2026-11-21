<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Stripe webhook handler with signature verification, event idempotency
 * and full order records. Configure the signing secret (whsec_...) in
 * Payment Gateways and point the Stripe webhook endpoint at:
 *   {site}/wp-json/{namespace}/webhook/stripe
 */
class KeyVault_Manager_Webhooks {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_routes'));
    }

    public function register_webhook_routes() {
        $namespace = defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1';

        register_rest_route($namespace, '/webhook/stripe', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_stripe_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Verify the Stripe-Signature header: v1 = HMAC-SHA256(timestamp.payload, whsec).
     */
    private function verify_signature($payload, $header, $secret) {
        if (empty($header) || empty($secret)) {
            return false;
        }

        $timestamp = '';
        $signatures = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === '' || empty($signatures)) {
            return false;
        }

        // Reject replays older than 5 minutes.
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    public function handle_stripe_webhook($request) {
        $payload  = (string) $request->get_body();
        $header   = (string) $request->get_header('stripe-signature');
        $settings = get_option('kvm_api_settings', array());
        $secret   = isset($settings['stripe_webhook_secret']) ? trim($settings['stripe_webhook_secret']) : '';

        if (empty($secret)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Webhook signing secret is not configured in Payment Gateways.',
            ), 400);
        }

        if (!$this->verify_signature($payload, $header, $secret)) {
            KeyVault_Manager_Logger::log('webhook', '', '', 400, 'Stripe webhook signature verification failed.');
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid signature.'), 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(array('received' => true), 200);
        }

        // Idempotency: Stripe retries deliveries; process each event once.
        $event_id = isset($event['id']) ? (string) $event['id'] : '';
        if ($event_id !== '' && !KeyVault_Manager_User_Auth::claim('stripe_evt_' . $event_id, DAY_IN_SECONDS)) {
            return new WP_REST_Response(array('received' => true), 200);
        }

        if ($event['type'] !== 'checkout.session.completed') {
            return new WP_REST_Response(array('received' => true), 200);
        }

        $session = isset($event['data']['object']) ? $event['data']['object'] : array();
        $payment_status = isset($session['payment_status']) ? $session['payment_status'] : '';
        if ($payment_status !== '' && $payment_status !== 'paid') {
            KeyVault_Manager_Logger::log('webhook', '', '', 200, 'Checkout session not paid: ' . $payment_status);
            return new WP_REST_Response(array('received' => true), 200);
        }

        $meta = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : array();
        $qty  = max(1, intval(isset($meta['quantity']) ? $meta['quantity'] : 1));
        $mode = (isset($meta['license_mode']) && $meta['license_mode'] === 'individual') ? 'individual' : 'multi';
        $is_lifetime = (isset($meta['is_lifetime']) && $meta['is_lifetime'] === '1');

        $email = sanitize_email(isset($session['customer_details']['email']) ? $session['customer_details']['email'] : (isset($meta['customer_email']) ? $meta['customer_email'] : ''));
        $name  = sanitize_text_field(isset($session['customer_details']['name']) ? $session['customer_details']['name'] : (isset($meta['customer_name']) ? $meta['customer_name'] : ''));
        if (empty($email)) {
            KeyVault_Manager_Logger::log('webhook', '', '', 200, 'Webhook session missing customer email.');
            return new WP_REST_Response(array('received' => true), 200);
        }

        $amount   = isset($session['amount_total']) ? floatval($session['amount_total']) / 100 : 0;
        $order_id = KeyVault_Manager_DB::generate_order_id();

        $keys = KeyVault_Manager_DB::get_instance()->generate_license_keys(
            $email,
            $name,
            $qty,
            $mode,
            $is_lifetime ? 'lifetime' : '1_year',
            'Stripe Checkout Payment',
            0,
            null,
            array('order_id' => $order_id, 'amount' => $amount, 'gateway' => 'Stripe')
        );

        global $wpdb;
        $wpdb->insert(KeyVault_Manager_DB::table('orders'), array(
            'order_id'       => $order_id,
            'customer_name'  => $name,
            'customer_email' => $email,
            'amount'         => $amount,
            'gateway'        => 'Stripe',
            'quantity'       => $qty,
            'license_mode'   => $mode,
            'license_keys'   => implode(', ', (array) $keys),
            'status'         => 'completed',
            'created_at'     => current_time('mysql'),
        ));

        KeyVault_Manager_Logger::log('webhook', '', '', 200, 'Order ' . $order_id . ' fulfilled via Stripe webhook.');

        // Never expose license keys in the webhook response.
        return new WP_REST_Response(array('received' => true), 200);
    }
}
