<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Public frontend: order/checkout, customer portal, auth flows.
 *
 * v6.0.0 security model:
 * - License fulfillment is only performed after server-side payment
 *   verification with Stripe/PayPal. URL parameters are never trusted.
 * - Portal sessions use a signed cookie (see class-user-auth.php).
 * - Portal users can only manage domains that belong to their own licenses.
 */
class KeyVault_Manager_Public {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $slug = KVM_SLUG;

        add_action('wp_enqueue_scripts', array($this, 'register_public_assets'));

        add_shortcode($slug . '_order_form', array($this, 'render_order_form'));
        add_shortcode($slug . '_login_form', array($this, 'render_login_form'));
        add_shortcode($slug . '_client_dashboard', array($this, 'render_customer_portal'));
        add_shortcode($slug . '_customer_portal', array($this, 'render_customer_portal'));

        add_action('wp_loaded', array($this, 'handle_form_submissions'), 10);
        add_action('wp_loaded', array($this, 'handle_unlink_action'), 10);
        add_action('wp_loaded', array($this, 'handle_logout'), 10);
        add_action('wp', array($this, 'maybe_send_nocache_headers'));
    }

    /**
     * Portal base URL: the custom login URL when configured, otherwise the
     * auto-created {slug}-account page.
     */
    public static function portal_url() {
        $custom = trim((string) get_option('kvm_custom_login_url', ''));
        if (!empty($custom)) {
            return esc_url_raw($custom);
        }
        return site_url('/' . get_option('kvm_plugin_slug', KVM_SLUG) . '-account');
    }

    public function get_url($args = array()) {
        $base_url = self::portal_url();
        if (empty($args)) {
            return $base_url;
        }
        $slug = KVM_SLUG;
        $prefixed_args = array();
        foreach ($args as $key => $value) {
            $prefixed_args[$slug . '_' . ltrim($key, '_')] = $value;
        }
        return add_query_arg($prefixed_args, $base_url);
    }

    /* ------------------------------------------------------------------
     * Assets — registered globally, enqueued only where shortcodes render.
     * ---------------------------------------------------------------- */

    public function register_public_assets() {
        $slug = KVM_SLUG;

        wp_register_style(
            $slug . '-frontend-style',
            KVM_URL . 'public/assets/css/frontend-style.css',
            array(),
            KVM_VERSION
        );
        wp_register_script(
            $slug . '-frontend-script',
            KVM_URL . 'public/assets/js/frontend-script.js',
            array('jquery'),
            KVM_VERSION,
            true
        );

        // Dynamic pricing and tier boundaries for the order form.
        wp_localize_script($slug . '-frontend-script', 'kvm_pricing', array(
            'starter_price' => floatval(get_option('kvm_price_starter', '49')),
            'starter_max'   => intval(get_option('kvm_starter_max', '9')),
            'pro_price'     => floatval(get_option('kvm_price_professional', '39')),
            'pro_max'       => intval(get_option('kvm_pro_max', '99')),
            'ent_price'     => floatval(get_option('kvm_price_enterprise', '30')),
        ));
    }

    private function enqueue_public_assets() {
        $slug = KVM_SLUG;
        wp_enqueue_style($slug . '-frontend-style');
        wp_enqueue_script($slug . '-frontend-script');
    }

    /**
     * Zero-caching headers for pages containing the order form (headers
     * are sent before rendering, not inside the shortcode).
     */
    public function maybe_send_nocache_headers() {
        if (!is_singular()) {
            return;
        }
        $post = get_post();
        if ($post && has_shortcode($post->post_content, KVM_SLUG . '_order_form')) {
            nocache_headers();
        }
    }

    /* ------------------------------------------------------------------
     * Views
     * ---------------------------------------------------------------- */

    public function render_order_form() {
        $this->enqueue_public_assets();
        $slug = KVM_SLUG;

        $ordered_param = $slug . '_ordered';
        if ((isset($_GET[$ordered_param]) && $_GET[$ordered_param] === '1') || (isset($_GET['ordered']) && $_GET['ordered'] === '1')) {
            return '<div class="kvm-wrap kvm-wrap--narrow"><div class="kvm-card kvm-success-card">'
                . '<div class="kvm-success-icon">🎉</div>'
                . '<h2>Order Completed Successfully!</h2>'
                . '<p>Your license key and customer portal account are now ready. Check your inbox for access details.</p>'
                . '</div></div>';
        }

        if (isset($_GET[$slug . '_err'])) {
            $err = sanitize_key(wp_unslash($_GET[$slug . '_err']));
            $messages = array(
                'payment_verification_failed' => 'We could not verify your payment with the payment provider. If you were charged, please contact support.',
                'payment_not_completed'       => 'Your payment has not been completed yet. Please try again or use a different payment method.',
                'missing_email'               => 'We could not determine the customer email for your order. Please contact support.',
            );
            $msg = isset($messages[$err]) ? $messages[$err] : '';
            if ($msg !== '') {
                return '<div class="kvm-wrap kvm-wrap--narrow"><div class="kvm-card"><div class="kvm-alert kvm-alert--error">' . esc_html($msg) . '</div>'
                    . '<p class="kvm-center"><a class="kvm-btn kvm-btn--primary" href="' . esc_url($this->get_url()) . '">Back to Order Form</a></p></div></div>';
            }
        }

        ob_start();
        include KVM_PATH . 'public/views/order-form.php';
        return ob_get_clean();
    }

    public function render_login_form() {
        $this->enqueue_public_assets();

        if (KeyVault_Manager_User_Auth::get_instance()->get_current_user()) {
            return '<div class="kvm-wrap kvm-wrap--narrow"><div class="kvm-card">'
                . '<div class="kvm-alert kvm-alert--success">You are already signed in.</div>'
                . '<p class="kvm-center"><a class="kvm-btn kvm-btn--primary" href="' . esc_url($this->get_url()) . '">Go to Your Dashboard</a></p>'
                . '</div></div>';
        }
        return $this->render_auth_views();
    }

    private function render_auth_views() {
        $this->enqueue_public_assets();
        ob_start();
        $slug  = KVM_SLUG;
        $action = isset($_GET[$slug . '_auth']) ? sanitize_key(wp_unslash($_GET[$slug . '_auth'])) : (isset($_GET['auth']) ? sanitize_key(wp_unslash($_GET['auth'])) : 'login');

        if ($action === 'forgot_pass' && file_exists(KVM_PATH . 'public/views/forgot-password.php')) {
            include KVM_PATH . 'public/views/forgot-password.php';
        } elseif ($action === 'reset_pass' && !empty($_GET[$slug . '_token']) && file_exists(KVM_PATH . 'public/views/reset-password.php')) {
            include KVM_PATH . 'public/views/reset-password.php';
        } else {
            include KVM_PATH . 'public/views/login.php';
        }
        return ob_get_clean();
    }

    public function render_customer_portal() {
        $user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
        if (!$user) {
            return $this->render_auth_views();
        }

        global $wpdb;
        $slug  = KVM_SLUG;
        $email = sanitize_email($user->email);

        $licenses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . KeyVault_Manager_DB::table('licenses') . "
             WHERE (user_id = %d OR customer_email = %s) AND status != 'parent_holder'
             ORDER BY id DESC",
            intval($user->id),
            $email
        ));

        // Aggregate activations in one query (no per-license queries).
        $acts_map         = array();
        $total_slots      = 0;
        $active_activations = 0;
        $license_ids      = array();
        foreach ($licenses as $lic) {
            $license_ids[] = intval($lic->id);
            $total_slots  += intval($lic->max_activations);
        }
        if (!empty($license_ids)) {
            $ids_in = implode(',', array_map('intval', $license_ids));
            $rows = $wpdb->get_results(
                "SELECT license_id, COUNT(*) AS c FROM " . KeyVault_Manager_DB::table('activations') . "
                 WHERE license_id IN ($ids_in) GROUP BY license_id"
            );
            foreach ($rows as $row) {
                $acts_map[intval($row->license_id)] = intval($row->c);
                $active_activations += intval($row->c);
            }
        }

        $total_licenses = count($licenses);
        $tab = isset($_GET[$slug . '_tab'])
            ? sanitize_key(wp_unslash($_GET[$slug . '_tab']))
            : (isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard');
        if (!in_array($tab, array('dashboard', 'licenses', 'downloads', 'profile', 'security'), true)) {
            $tab = 'dashboard';
        }

        $public = $this;

        ob_start();
        include KVM_PATH . 'public/views/client-portal.php';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------
     * Form handlers (run on wp_loaded — before any output).
     * ---------------------------------------------------------------- */

    public function handle_form_submissions() {
        $slug = KVM_SLUG;

        // 1. Order form submission → gateway checkout.
        if (isset($_POST[$slug . '_submit_order_form'])) {
            $this->process_order_submission(); // exits on all paths
            return;
        }

        // 2. Gateway return → verified fulfillment.
        $this->process_gateway_return();

        // 3. Portal login.
        if (isset($_POST[$slug . '_submit_login'])) {
            if (!isset($_POST[$slug . '_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$slug . '_login_nonce'])), $slug . '_login_action')) {
                wp_die(esc_html__('Security check failed. Please go back and try again.', 'cdf'), 403);
            }
            $email = isset($_POST[$slug . '_login_email']) ? sanitize_email(wp_unslash($_POST[$slug . '_login_email'])) : '';
            $pass  = isset($_POST[$slug . '_login_pass']) ? wp_unslash($_POST[$slug . '_login_pass']) : '';

            $result = KeyVault_Manager_User_Auth::get_instance()->authenticate($email, $pass);
            if (is_wp_error($result)) {
                wp_safe_redirect($this->get_url(array('auth' => 'login', 'err' => 'too_many_attempts')));
                exit;
            }
            if ($result) {
                wp_safe_redirect($this->get_url());
                exit;
            }
            wp_safe_redirect($this->get_url(array('auth' => 'login', 'err' => 'invalid_login')));
            exit;
        }

        // 4. Forgot password.
        if (isset($_POST[$slug . '_submit_forgot_pass'])) {
            $this->process_forgot_password();
            return;
        }

        // 5. Reset password.
        if (isset($_POST[$slug . '_submit_reset_pass'])) {
            $this->process_reset_password();
            return;
        }

        // 6. Portal change password.
        if (isset($_POST[$slug . '_submit_change_pass'])) {
            $this->process_change_password();
        }
    }

    private function verify_form_nonce($action, $field) {
        if (!isset($_POST[$field]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field])), $action)) {
            wp_die(esc_html__('Security check failed. Please go back and try again.', 'cdf'), 403);
        }
    }

    private function process_order_submission() {
        $slug = KVM_SLUG;

        $enable_nonce = get_option('kvm_enable_nonce', 1);
        if ($enable_nonce) {
            $this->verify_form_nonce($slug . '_order_form_action', $slug . '_order_form_nonce');
        }

        $qty  = isset($_POST[$slug . '_quantity']) ? intval($_POST[$slug . '_quantity']) : 0;
        if ($qty < 1 || $qty > 10000) {
            wp_die(esc_html__('Invalid website slot quantity.', 'cdf'), 400);
        }
        $mode    = (isset($_POST[$slug . '_license_mode']) && $_POST[$slug . '_license_mode'] === 'individual') ? 'individual' : 'multi';
        $gateway = (isset($_POST['payment_method']) && $_POST['payment_method'] === 'paypal') ? 'paypal' : 'stripe';

        $current_user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
        $name  = isset($_POST[$slug . '_customer_name']) ? sanitize_text_field(wp_unslash($_POST[$slug . '_customer_name'])) : ($current_user ? $current_user->name : '');
        $email = isset($_POST[$slug . '_customer_email']) ? sanitize_email(wp_unslash($_POST[$slug . '_customer_email'])) : ($current_user ? $current_user->email : '');

        if (empty($name) || empty($email)) {
            wp_die(esc_html__('Customer name and email address are required.', 'cdf'), 400);
        }

        // Server-side pricing (client values are never trusted).
        $starter_price = floatval(get_option('kvm_price_starter', '49'));
        $starter_max   = intval(get_option('kvm_starter_max', '9'));
        $pro_price     = floatval(get_option('kvm_price_professional', '39'));
        $pro_max       = intval(get_option('kvm_pro_max', '99'));
        $ent_price     = floatval(get_option('kvm_price_enterprise', '30'));

        $is_lifetime = ($qty > $pro_max);
        $base_rate   = $starter_price;
        if ($qty > $pro_max) {
            $base_rate = $ent_price;
        } elseif ($qty > $starter_max) {
            $base_rate = $pro_price;
        }
        $total_amount = round($qty * $base_rate, 2);

        $settings   = get_option('kvm_api_settings', array());
        $is_sandbox = ('sandbox' === get_option('kvm_environment', 'sandbox'));

        if ('stripe' === $gateway) {
            $stripe_secret = $is_sandbox ? trim(isset($settings['stripe_secret_test']) ? $settings['stripe_secret_test'] : '') : trim(isset($settings['stripe_secret_live']) ? $settings['stripe_secret_live'] : '');
            if (empty($stripe_secret)) {
                wp_die(esc_html__('Stripe is not configured for the selected environment. Please contact support.', 'cdf'), 500);
            }

            $session_response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $stripe_secret,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => array(
                    'payment_method_types[0]'             => 'card',
                    'mode'                                => 'payment',
                    'customer_email'                      => $email,
                    'line_items[0][price_data][currency]' => 'usd',
                    'line_items[0][price_data][unit_amount]' => round($total_amount * 100),
                    'line_items[0][price_data][product_data][name]' => ucwords(str_replace('-', ' ', KVM_SLUG)) . ' License (' . $qty . ' Website Slots)',
                    'line_items[0][quantity]'             => 1,
                    'success_url'                         => $this->get_url(array('ordered' => '1', 'session_id' => '{CHECKOUT_SESSION_ID}')),
                    'cancel_url'                          => esc_url_raw($this->get_url(array('cancelled' => '1'))),
                    'metadata[customer_name]'             => $name,
                    'metadata[customer_email]'            => $email,
                    'metadata[quantity]'                  => $qty,
                    'metadata[license_mode]'              => $mode,
                    'metadata[is_lifetime]'               => $is_lifetime ? '1' : '0',
                ),
            ));

            if (is_wp_error($session_response)) {
                wp_die(esc_html__('Stripe Connection Error: ', 'cdf') . esc_html($session_response->get_error_message()), 500);
            }
            $response_code = wp_remote_retrieve_response_code($session_response);
            $session_data  = json_decode(wp_remote_retrieve_body($session_response), true);
            if ($response_code !== 200 || empty($session_data['url'])) {
                $error_message = isset($session_data['error']['message']) ? $session_data['error']['message'] : 'Unknown Stripe API error response.';
                wp_die(esc_html__('Stripe Checkout Error: ', 'cdf') . esc_html($error_message), 500);
            }
            wp_redirect(esc_url_raw($session_data['url']));
            exit;
        }

        // PayPal checkout.
        $paypal_client = $is_sandbox ? trim(isset($settings['paypal_client_test']) ? $settings['paypal_client_test'] : '') : trim(isset($settings['paypal_client_live']) ? $settings['paypal_client_live'] : '');
        $paypal_secret = $is_sandbox ? trim(isset($settings['paypal_secret_test']) ? $settings['paypal_secret_test'] : '') : trim(isset($settings['paypal_secret_live']) ? $settings['paypal_secret_live'] : '');
        if (empty($paypal_client) || empty($paypal_secret)) {
            wp_die(esc_html__('PayPal is not configured for the selected environment. Please contact support.', 'cdf'), 500);
        }

        $api_base      = $is_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $auth_response = wp_remote_post($api_base . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($paypal_client . ':' . $paypal_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ));
        if (is_wp_error($auth_response)) {
            wp_die(esc_html__('PayPal Connection Error: ', 'cdf') . esc_html($auth_response->get_error_message()), 500);
        }
        $auth_data    = json_decode(wp_remote_retrieve_body($auth_response), true);
        $access_token = isset($auth_data['access_token']) ? $auth_data['access_token'] : '';
        if (empty($access_token)) {
            wp_die(esc_html__('PayPal Authentication Failed: invalid API credentials.', 'cdf'), 500);
        }

        $custom_id = wp_json_encode(array('qty' => $qty, 'mode' => $mode, 'lt' => $is_lifetime ? 1 : 0));

        $order_response = wp_remote_post($api_base . '/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(array(
                    'amount'    => array('currency_code' => 'USD', 'value' => number_format($total_amount, 2, '.', '')),
                    'custom_id' => $custom_id,
                )),
                'application_context' => array(
                    // PayPal appends ?token={ORDER_ID}&PayerID=... to this URL on return.
                    'return_url' => $this->get_url(array('ordered' => '1')),
                    'cancel_url' => $this->get_url(array('cancelled' => '1')),
                ),
            )),
        ));
        if (is_wp_error($order_response)) {
            wp_die(esc_html__('PayPal Order Error: ', 'cdf') . esc_html($order_response->get_error_message()), 500);
        }

        $order_data  = json_decode(wp_remote_retrieve_body($order_response), true);
        $approve_url = '';
        foreach ((array) (isset($order_data['links']) ? $order_data['links'] : array()) as $link) {
            if (isset($link['rel']) && $link['rel'] === 'approve') {
                $approve_url = isset($link['href']) ? $link['href'] : '';
                break;
            }
        }
        if (!empty($approve_url)) {
            wp_redirect(esc_url_raw($approve_url));
            exit;
        }
        wp_die(esc_html__('PayPal Error: unable to generate an approval link.', 'cdf'), 500);
    }

    private function process_gateway_return() {
        $slug    = KVM_SLUG;
        $ordered = isset($_GET[$slug . '_ordered']) ? sanitize_text_field(wp_unslash($_GET[$slug . '_ordered'])) : (isset($_GET['ordered']) ? sanitize_text_field(wp_unslash($_GET['ordered'])) : '');

        if ($ordered !== '1') {
            if (isset($_GET[$slug . '_cancelled']) || isset($_GET['cancelled'])) {
                // Checkout abandoned / cancelled — admin alert (no sensitive data in URLs).
                KeyVault_Manager_Emails::get_instance()->send_event_email('admin_payment_issue', '', array(
                    'gateway' => isset($_GET[$slug . '_gateway']) ? sanitize_text_field(wp_unslash($_GET[$slug . '_gateway'])) : 'Unknown',
                ));
            }
            return;
        }

        // Stripe path: verify the session server-side, then fulfill from metadata.
        $session_id = isset($_GET[$slug . '_session_id']) ? sanitize_text_field(wp_unslash($_GET[$slug . '_session_id'])) : (isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '');
        if ($session_id !== '') {
            $this->fulfill_stripe($session_id);
            return;
        }

        // PayPal path: the order id arrives in the `token` parameter that PayPal
        // appends to the return URL. Capture the order server-side, then fulfill.
        $pp_order = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if ($pp_order !== '') {
            $this->fulfill_paypal($pp_order);
            return;
        }

        // Legacy v5.x return URLs carried unverified parameters and are ignored
        // for security: fulfillment now requires a verifiable gateway reference.
    }

    private function gateway_credentials($gateway) {
        $settings   = get_option('kvm_api_settings', array());
        $is_sandbox = ('sandbox' === get_option('kvm_environment', 'sandbox'));
        if ($gateway === 'stripe') {
            $secret = $is_sandbox
                ? (isset($settings['stripe_secret_test']) ? trim($settings['stripe_secret_test']) : '')
                : (isset($settings['stripe_secret_live']) ? trim($settings['stripe_secret_live']) : '');
            return array($secret, $is_sandbox);
        }
        $client_id = $is_sandbox
            ? (isset($settings['paypal_client_test']) ? trim($settings['paypal_client_test']) : '')
            : (isset($settings['paypal_client_live']) ? trim($settings['paypal_client_live']) : '');
        $secret    = $is_sandbox
            ? (isset($settings['paypal_secret_test']) ? trim($settings['paypal_secret_test']) : '')
            : (isset($settings['paypal_secret_live']) ? trim($settings['paypal_secret_live']) : '');
        return array($client_id, $secret, $is_sandbox);
    }

    private function redirect_payment_error($code) {
        wp_safe_redirect($this->get_url(array('ordered' => '1', 'err' => $code)));
        exit;
    }

    private function fulfill_stripe($session_id) {
        $transient_key = KVM_SLUG . '_processed_' . md5('stripe_' . $session_id);
        if (KeyVault_Manager_User_Auth::has_claim($transient_key)) {
            return; // Already fulfilled; success page renders.
        }

        list($secret) = $this->gateway_credentials('stripe');
        if (empty($secret)) {
            $this->redirect_payment_error('payment_verification_failed');
        }

        $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($session_id), array(
            'headers' => array('Authorization' => 'Bearer ' . $secret),
            'timeout' => 30,
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->redirect_payment_error('payment_verification_failed');
        }

        $session = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($session)) {
            $this->redirect_payment_error('payment_verification_failed');
        }

        $paid = (isset($session['payment_status']) && $session['payment_status'] === 'paid')
            || (isset($session['status']) && $session['status'] === 'complete');
        if (!$paid) {
            $this->redirect_payment_error('payment_not_completed');
        }

        $meta = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : array();
        $qty  = max(1, intval(isset($meta['quantity']) ? $meta['quantity'] : 1));
        $mode = (isset($meta['license_mode']) && $meta['license_mode'] === 'individual') ? 'individual' : 'multi';
        $is_lifetime = (isset($meta['is_lifetime']) && $meta['is_lifetime'] === '1');
        $name  = isset($meta['customer_name']) ? sanitize_text_field($meta['customer_name']) : (isset($session['customer_details']['name']) ? sanitize_text_field($session['customer_details']['name']) : 'Customer');
        $email = isset($meta['customer_email']) ? sanitize_email($meta['customer_email']) : (isset($session['customer_details']['email']) ? sanitize_email($session['customer_details']['email']) : '');
        $amount = isset($session['amount_total']) ? floatval($session['amount_total']) / 100 : 0;

        if (empty($email)) {
            $this->redirect_payment_error('missing_email');
        }

        // Atomic claim: guard against concurrent double fulfillment.
        if (!KeyVault_Manager_User_Auth::claim($transient_key, DAY_IN_SECONDS)) {
            return; // Another request is already fulfilling this payment.
        }

        $this->fulfill($email, $name, $qty, $mode, $is_lifetime, $amount, 'Stripe');
    }

    private function fulfill_paypal($pp_order_id) {
        $transient_key = KVM_SLUG . '_processed_' . md5('paypal_' . $pp_order_id);
        if (KeyVault_Manager_User_Auth::has_claim($transient_key)) {
            return;
        }

        list($client_id, $client_secret, $is_sandbox) = $this->gateway_credentials('paypal');
        if (empty($client_id) || empty($client_secret)) {
            $this->redirect_payment_error('payment_verification_failed');
        }

        $api_base      = $is_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $auth_response = wp_remote_post($api_base . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ));
        if (is_wp_error($auth_response)) {
            $this->redirect_payment_error('payment_verification_failed');
        }
        $auth_data    = json_decode(wp_remote_retrieve_body($auth_response), true);
        $access_token = isset($auth_data['access_token']) ? $auth_data['access_token'] : '';
        if (empty($access_token)) {
            $this->redirect_payment_error('payment_verification_failed');
        }

        $capture_response = wp_remote_post($api_base . '/v2/checkout/orders/' . rawurlencode($pp_order_id) . '/capture', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => '{}',
            'timeout' => 30,
        ));
        $body     = wp_remote_retrieve_body($capture_response);
        $response = json_decode($body, true);

        // Already captured (duplicate return visit): treat as success without re-fulfilling.
        if (isset($response['details'][0]['issue']) && $response['details'][0]['issue'] === 'ORDER_ALREADY_CAPTURED') {
            KeyVault_Manager_User_Auth::claim($transient_key, DAY_IN_SECONDS);
            return;
        }

        $status = isset($response['status']) ? $response['status'] : '';
        if ($status !== 'COMPLETED') {
            $this->redirect_payment_error('payment_not_completed');
        }

        $units  = isset($response['purchase_units'][0]) ? $response['purchase_units'][0] : array();
        $amount = 0;
        if (isset($units['payments']['captures'][0]['amount']['value'])) {
            $amount = floatval($units['payments']['captures'][0]['amount']['value']);
        }

        $custom = array();
        if (!empty($units['custom_id'])) {
            $decoded = json_decode($units['custom_id'], true);
            if (is_array($decoded)) {
                $custom = $decoded;
            }
        }
        $qty  = max(1, intval(isset($custom['qty']) ? $custom['qty'] : 1));
        $mode = (isset($custom['mode']) && $custom['mode'] === 'individual') ? 'individual' : 'multi';
        $is_lifetime = !empty($custom['lt']);

        $payer  = isset($response['payer']) ? $response['payer'] : array();
        $email  = isset($payer['email_address']) ? sanitize_email($payer['email_address']) : '';
        $name   = '';
        if (isset($payer['name'])) {
            $name = trim((isset($payer['name']['given_name']) ? $payer['name']['given_name'] : '') . ' ' . (isset($payer['name']['surname']) ? $payer['name']['surname'] : ''));
        }
        $name = sanitize_text_field($name);
        if ($name === '') {
            $name = 'Customer';
        }

        if (empty($email)) {
            $this->redirect_payment_error('missing_email');
        }

        // Atomic claim: guard against concurrent double fulfillment.
        if (!KeyVault_Manager_User_Auth::claim($transient_key, DAY_IN_SECONDS)) {
            return;
        }

        $this->fulfill($email, $name, $qty, $mode, $is_lifetime, $amount, 'PayPal');
    }

    /**
     * Verified fulfillment: create/fetch portal user, generate keys, record order.
     */
    private function fulfill($email, $name, $qty, $mode, $is_lifetime, $amount, $gateway_label) {
        global $wpdb;

        $user_auth = KeyVault_Manager_User_Auth::get_instance();
        $auth_res  = $user_auth->get_or_create_user($name, $email);
        $user_id   = ($auth_res['user'] && isset($auth_res['user']->id)) ? intval($auth_res['user']->id) : 0;

        $order_id = KeyVault_Manager_DB::generate_order_id();

        $generated_keys = KeyVault_Manager_DB::get_instance()->generate_license_keys(
            $email,
            $name,
            $qty,
            $mode,
            $is_lifetime ? 'lifetime' : '1_year',
            $gateway_label . ' Checkout Payment',
            $user_id,
            null,
            array('order_id' => $order_id, 'amount' => $amount, 'gateway' => $gateway_label)
        );

        $keys_string = implode(', ', (array) $generated_keys);

        $wpdb->insert(KeyVault_Manager_DB::table('orders'), array(
            'order_id'       => $order_id,
            'customer_name'  => $name,
            'customer_email' => $email,
            'amount'         => $amount,
            'gateway'        => $gateway_label,
            'quantity'       => $qty,
            'license_mode'   => $mode,
            'license_keys'   => $keys_string,
            'status'         => 'completed',
            'created_at'     => current_time('mysql'),
        ));
    }

    private function process_forgot_password() {
        $slug = KVM_SLUG;
        $this->verify_form_nonce($slug . '_forgot_pass_action', $slug . '_forgot_pass_nonce');

        $email = isset($_POST[$slug . '_forgot_email']) ? sanitize_email(wp_unslash($_POST[$slug . '_forgot_email'])) : '';
        if (!empty($email) && KeyVault_Manager_User_Auth::throttle('forgot_' . md5($email), 5, HOUR_IN_SECONDS)) {
            global $wpdb;
            $table = KeyVault_Manager_DB::table('users');
            $user  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE email = %s", $email));

            if ($user) {
                $auth       = KeyVault_Manager_User_Auth::get_instance();
                $token      = $auth->create_reset_token($user->id);
                $reset_link = $this->get_url(array('auth' => 'reset_pass', 'token' => $token));

                KeyVault_Manager_Emails::get_instance()->send_event_email('user_password_reset', $email, array(
                    'customer_name'  => $user->name,
                    'customer_email' => $email,
                    'reset_link'     => $reset_link,
                ));
            }
            // Intentionally identical response whether or not the account exists.
        }

        wp_safe_redirect($this->get_url(array('auth' => 'forgot_pass', 'sent' => '1')));
        exit;
    }

    private function process_reset_password() {
        $slug = KVM_SLUG;
        $this->verify_form_nonce($slug . '_reset_pass_action', $slug . '_reset_pass_nonce');

        $token    = isset($_POST[$slug . '_reset_token']) ? sanitize_text_field(wp_unslash($_POST[$slug . '_reset_token'])) : '';
        $new_pass = isset($_POST[$slug . '_new_password']) ? wp_unslash($_POST[$slug . '_new_password']) : '';
        $confirm  = isset($_POST[$slug . '_confirm_password']) ? wp_unslash($_POST[$slug . '_confirm_password']) : '';

        $auth = KeyVault_Manager_User_Auth::get_instance();
        $uid  = $auth->validate_reset_token($token);

        $valid = $uid > 0
            && strlen($new_pass) >= 8
            && $new_pass === $confirm;

        if ($valid) {
            $auth->set_password($uid, $new_pass);
            $auth->consume_reset_token($token);

            global $wpdb;
            $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . KeyVault_Manager_DB::table('users') . " WHERE id = %d", $uid));
            KeyVault_Manager_Emails::get_instance()->send_event_email('user_password_changed', $user ? $user->email : '', array(
                'customer_name' => $user ? $user->name : '',
            ));

            wp_safe_redirect($this->get_url(array('auth' => 'login', 'msg' => 'pass_reset_success')));
            exit;
        }

        wp_safe_redirect($this->get_url(array('auth' => 'reset_pass', 'token' => $token, 'err' => 'invalid_reset')));
        exit;
    }

    private function process_change_password() {
        $slug = KVM_SLUG;
        $this->verify_form_nonce($slug . '_change_pass_action', $slug . '_change_pass_nonce');

        $user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
        if (!$user) {
            wp_safe_redirect($this->get_url(array('auth' => 'login')));
            exit;
        }

        $old_pass = isset($_POST[$slug . '_old_password']) ? wp_unslash($_POST[$slug . '_old_password']) : '';
        $new_pass = isset($_POST[$slug . '_new_password']) ? wp_unslash($_POST[$slug . '_new_password']) : '';
        $confirm  = isset($_POST[$slug . '_confirm_password']) ? wp_unslash($_POST[$slug . '_confirm_password']) : '';

        $auth = KeyVault_Manager_User_Auth::get_instance();
        if (strlen($new_pass) < 8 || $new_pass !== $confirm || !$auth->change_password($user->id, $old_pass, $new_pass)) {
            wp_safe_redirect($this->get_url(array('tab' => 'security', 'err' => 'pass_mismatch')));
            exit;
        }

        KeyVault_Manager_Emails::get_instance()->send_event_email('user_password_changed', $user->email, array(
            'customer_name' => $user->name,
        ));

        wp_safe_redirect($this->get_url(array('tab' => 'security', 'msg' => 'pass_changed')));
        exit;
    }

    /* ------------------------------------------------------------------
     * Portal actions: unlink (ownership-checked) and logout.
     * ---------------------------------------------------------------- */

    public function handle_unlink_action() {
        $slug = KVM_SLUG;
        if (empty($_GET[$slug . '_action']) || $_GET[$slug . '_action'] !== 'unlink_user') {
            return;
        }

        $act_id = isset($_GET['act_id']) ? intval($_GET['act_id']) : 0;
        $nonce  = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if ($act_id <= 0 || !wp_verify_nonce($nonce, $slug . '_unlink_user_' . $act_id)) {
            return;
        }

        $user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
        if (!$user) {
            wp_safe_redirect($this->get_url(array('auth' => 'login')));
            exit;
        }

        global $wpdb;
        $acts = KeyVault_Manager_DB::table('activations');
        $lics = KeyVault_Manager_DB::table('licenses');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT a.id, a.domain, a.license_id, l.user_id AS lic_user_id, l.customer_email AS lic_email, l.license_key AS lic_key
             FROM $acts a INNER JOIN $lics l ON l.id = a.license_id
             WHERE a.id = %d",
            $act_id
        ));

        if (!$row) {
            wp_safe_redirect($this->get_url(array('tab' => 'licenses')));
            exit;
        }

        // Ownership check — customers may only unlink their own domains.
        $owns = intval($row->lic_user_id) === intval($user->id)
            || sanitize_email($row->lic_email) === sanitize_email($user->email);
        if (!$owns) {
            wp_safe_redirect($this->get_url(array('tab' => 'licenses', 'err' => 'not_permitted')));
            exit;
        }

        $wpdb->delete($acts, array('id' => $act_id));

        $email_data = array(
            'domain'        => $row->domain,
            'license_keys'  => $row->lic_key,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
        );
        KeyVault_Manager_Emails::get_instance()->send_event_email('admin_site_unlinked', '', $email_data);
        KeyVault_Manager_Emails::get_instance()->send_event_email('user_domain_updated', $user->email, $email_data);

        wp_safe_redirect($this->get_url(array('tab' => 'licenses', 'msg' => 'domain_unlinked')));
        exit;
    }

    public function handle_logout() {
        $slug = KVM_SLUG;
        if (empty($_GET[$slug . '_action']) || $_GET[$slug . '_action'] !== 'logout') {
            return;
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, $slug . '_logout')) {
            wp_die(esc_html__('Security check failed.', 'cdf'), 403);
        }
        KeyVault_Manager_User_Auth::get_instance()->logout();
        wp_safe_redirect($this->get_url(array('auth' => 'login', 'msg' => 'logged_out')));
        exit;
    }
}
