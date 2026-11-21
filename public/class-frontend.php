<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Public {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $slug = KVM_SLUG;

        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        
        add_shortcode($slug . '_order_form', array($this, 'render_order_form'));
        add_shortcode($slug . '_login_form', array($this, 'render_login_form'));
        add_shortcode($slug . '_client_dashboard', array($this, 'render_customer_portal'));
        add_shortcode($slug . '_customer_portal', array($this, 'render_customer_portal'));

        add_action('wp_loaded', array($this, 'handle_form_submissions'));
        add_action('wp_loaded', array($this, 'handle_unlink_action'));
    }

    public function get_url($args = array()) {
        $slug = KVM_SLUG;
        $base_url = site_url('/' . $slug . '-account');

        if (empty($args)) {
            return $base_url;
        }

        $prefixed_args = array();
        foreach ($args as $key => $value) {
            $prefixed_args[$slug . '_' . ltrim($key, '_')] = $value;
        }

        return add_query_arg($prefixed_args, $base_url);
    }
    
    public function enqueue_public_assets() {
        $slug = KVM_SLUG;

        wp_enqueue_style($slug . '-frontend-style', KVM_URL . 'public/assets/css/frontend-style.css', array(), KVM_VERSION);
        wp_enqueue_script($slug . '-frontend-script', KVM_URL . 'public/assets/js/frontend-script.js', array('jquery'), KVM_VERSION, true);

        // Pass dynamic database prices and tier boundaries to JavaScript
        wp_localize_script($slug . '-frontend-script', 'kvm_pricing', array(
            'starter_price' => floatval(get_option($slug . '_price_starter', '49')),
            'starter_max'   => intval(get_option($slug . '_starter_max', '9')),
            'pro_price'     => floatval(get_option($slug . '_price_professional', '39')),
            'pro_max'       => intval(get_option($slug . '_pro_max', '99')),
            'ent_price'     => floatval(get_option($slug . '_price_enterprise', '30')),
        ));
    }

    public function render_order_form() {
        // Prevent browser caching so order form is always fresh
        nocache_headers();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        $slug = KVM_SLUG;
        $ordered_param = $slug . '_ordered';
        $price_starter = floatval(get_option($slug . '_price_starter', '49'));

        if ((isset($_GET[$ordered_param]) && $_GET[$ordered_param] === '1') || (isset($_GET['ordered']) && $_GET['ordered'] === '1')) {
            return '<div class="kvm-wrap" style="max-width:680px; margin:40px auto;"><div class="kvm-card" style="padding:50px; text-align:center;"><div style="font-size:48px; margin-bottom:15px;">🎉</div><h2 style="color:#10b981; font-size:26px; margin:0 0 10px 0; font-weight:800;">Order Completed Successfully!</h2><p style="color:#64748b; font-size:15px; margin:0;">Your license key and customer portal account are now ready. Check your inbox for access details.</p></div></div>';
        }
        ob_start();
        include KVM_PATH . 'public/views/order-form.php';
        return ob_get_clean();
    }

    public function render_login_form() {
        if (KeyVault_Manager_User_Auth::get_instance()->get_current_user()) {
            echo '<script>window.location.href="' . esc_url($this->get_url()) . '";</script>';
            return;
        }
        return $this->render_auth_views();
    }

    private function render_auth_views() {
        ob_start();
        $slug = KVM_SLUG;
        $action = sanitize_text_field($_GET[$slug . '_auth'] ?? $_GET['auth'] ?? 'login');

        if ($action === 'forgot_pass' && file_exists(KVM_PATH . 'public/views/forgot-password.php')) {
            include KVM_PATH . 'public/views/forgot-password.php';
        } elseif ($action === 'reset_pass' && !empty($_GET[$slug . '_token'] ?? $_GET['token']) && file_exists(KVM_PATH . 'public/views/reset-password.php')) {
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
        $slug = KVM_SLUG;
        
        $licenses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}{$slug}_licenses WHERE (user_id = %d OR customer_email = %s) AND status != 'parent_holder' ORDER BY id DESC",
            intval($user->id),
            sanitize_email($user->email)
        ));

        ob_start();
        include KVM_PATH . 'public/views/client-portal.php';
        return ob_get_clean();
    }

    public function handle_form_submissions() {
        $slug = KVM_SLUG;
        $account_url = $this->get_url();

        // ==========================================
        // 1. Order Form Submission & Gateway Checkout
        // ==========================================
        if (isset($_POST[$slug . '_submit_order_form'])) {
            // Check WordPress Nonce Security if enabled in settings
            $enable_nonce = get_option('kvm_enable_nonce', 1);
            if ($enable_nonce) {
                if (!isset($_POST[$slug . '_order_form_nonce']) || !wp_verify_nonce($_POST[$slug . '_order_form_nonce'], $slug . '_order_form_action')) {
                    wp_die('Security check failed (Nonce verification error). Please refresh the form and try again.');
                }
            }

            $qty = intval($_POST[$slug . '_quantity']);
            $mode = sanitize_text_field($_POST[$slug . '_license_mode'] ?? 'multi');
            $chosen_gateway = sanitize_text_field($_POST['payment_method'] ?? 'stripe');

            // Check if user is logged in to pre-fill or bind automatically
            $current_user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
            $name  = sanitize_text_field($_POST[$slug . '_customer_name'] ?? ($current_user ? $current_user->name : ''));
            $email = sanitize_email($_POST[$slug . '_customer_email'] ?? ($current_user ? $current_user->email : ''));

            // Fetch dynamic pricing boundaries and rates from database
            $starter_price = floatval(get_option($slug . '_price_starter', '49'));
            $starter_max   = intval(get_option($slug . '_starter_max', '9'));
            $pro_price     = floatval(get_option($slug . '_price_professional', '39'));
            $pro_max       = intval(get_option($slug . '_pro_max', '99'));
            $ent_price     = floatval(get_option($slug . '_price_enterprise', '30'));

            $is_lifetime = ($qty > $pro_max);
            $base_rate = $starter_price;
            if ($qty > $pro_max) {
                $base_rate = $ent_price;
            } elseif ($qty > $starter_max) {
                $base_rate = $pro_price;
            }
            $total_amount = $qty * $base_rate;

            // Fetch Gateway Settings & Dynamic Environment Mode (sandbox vs live)
            $settings   = get_option('kvm_api_settings', array());
            $is_sandbox = ('sandbox' === get_option('kvm_environment', 'sandbox'));

            $stripe_secret = $is_sandbox ? trim($settings['stripe_secret_test'] ?? '') : trim($settings['stripe_secret_live'] ?? '');
            $paypal_client = $is_sandbox ? trim($settings['paypal_client_test'] ?? '') : trim($settings['paypal_client_live'] ?? '');
            $paypal_secret = $is_sandbox ? trim($settings['paypal_secret_test'] ?? '') : trim($settings['paypal_secret_live'] ?? '');

            // ==========================================
            // STRIPE CHECKOUT SESSION INTEGRATION
            // ==========================================
            if ('stripe' === $chosen_gateway) {
                if (empty($stripe_secret)) {
                    wp_die('Error: Stripe Secret Key is missing for ' . ($is_sandbox ? 'Sandbox' : 'Live') . ' mode. Please configure it in Payment Gateways.');
                }

                $session_response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $stripe_secret,
                        'Content-Type'  => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'payment_method_types[0]'             => 'card',
                        'mode'                                => 'payment',
                        'customer_email'                      => $email,
                        'line_items[0][price_data][currency]' => 'usd',
                        'line_items[0][price_data][unit_amount]' => round($total_amount * 100),
                        'line_items[0][price_data][product_data][name]' => ucwords(str_replace('-', ' ', $slug)) . ' License (' . $qty . ' Website Slots)',
                        'line_items[0][quantity]'             => 1,
                        'success_url'                         => $this->get_url(array('ordered' => '1', 'session_id' => '{CHECKOUT_SESSION_ID}', 'name' => urlencode($name), 'email' => urlencode($email), 'qty' => $qty, 'mode' => $mode, 'lifetime' => $is_lifetime ? 1 : 0, 'amount' => $total_amount, 'gateway' => 'Stripe')),
                        'cancel_url'                          => esc_url_raw($this->get_url(array('cancelled' => '1'))),
                        'metadata[customer_name]'             => $name,
                        'metadata[customer_email]'            => $email,
                        'metadata[quantity]'                  => $qty,
                        'metadata[license_mode]'              => $mode,
                        'metadata[is_lifetime]'               => $is_lifetime ? '1' : '0',
                    ],
                ]);

                if (is_wp_error($session_response)) {
                    wp_die('Stripe Connection Error: ' . esc_html($session_response->get_error_message()));
                }

                $response_code = wp_remote_retrieve_response_code($session_response);
                $session_data = json_decode(wp_remote_retrieve_body($session_response), true);

                if ($response_code !== 200 || empty($session_data['url'])) {
                    $error_message = $session_data['error']['message'] ?? 'Unknown Stripe API error response.';
                    wp_die('Stripe Checkout Error (' . $response_code . '): ' . esc_html($error_message));
                }

                wp_redirect(esc_url_raw($session_data['url']));
                exit;
            } 
            // ==========================================
            // PAYPAL CHECKOUT INTEGRATION
            // ==========================================
            elseif ('paypal' === $chosen_gateway) {
                if (empty($paypal_client) || empty($paypal_secret)) {
                    wp_die('Error: PayPal Client ID or Secret is missing for ' . ($is_sandbox ? 'Sandbox' : 'Live') . ' mode. Please configure it in Payment Gateways.');
                }

                $api_base = $is_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
                
                $auth_response = wp_remote_post($api_base . '/v1/oauth2/token', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($paypal_client . ':' . $paypal_secret),
                        'Content-Type'  => 'application/x-www-form-urlencoded',
                    ],
                    'body' => 'grant_type=client_credentials',
                ]);

                if (is_wp_error($auth_response)) {
                    wp_die('PayPal Connection Error: ' . esc_html($auth_response->get_error_message()));
                }

                $auth_data = json_decode(wp_remote_retrieve_body($auth_response), true);
                $access_token = $auth_data['access_token'] ?? '';

                if (empty($access_token)) {
                    wp_die('PayPal Authentication Failed: Invalid API Client ID or Secret provided.');
                }

                $order_response = wp_remote_post($api_base . '/v2/checkout/orders', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => json_encode([
                        'intent' => 'CAPTURE',
                        'purchase_units' => [[
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => number_format($total_amount, 2, '.', '')
                            ]
                        ]],
                        'application_context' => [
                            'return_url' => $this->get_url(array('ordered' => '1', 'gateway' => 'PayPal', 'name' => urlencode($name), 'email' => urlencode($email), 'qty' => $qty, 'mode' => $mode, 'amount' => $total_amount, 'lifetime' => $is_lifetime ? 1 : 0)),
                            'cancel_url' => $this->get_url(array('cancelled' => '1')),
                        ]
                    ]),
                ]);

                if (is_wp_error($order_response)) {
                    wp_die('PayPal Order Error: ' . esc_html($order_response->get_error_message()));
                }

                $order_data = json_decode(wp_remote_retrieve_body($order_response), true);
                $approve_url = '';
                foreach ($order_data['links'] ?? [] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approve_url = $link['href'];
                        break;
                    }
                }

                if (!empty($approve_url)) {
                    wp_redirect(esc_url_raw($approve_url));
                    exit;
                } else {
                    wp_die('PayPal Error: Unable to generate approval link.');
                }
            }
        }

        // ==========================================
        // 2. Gateway Return & License Fulfillment
        // ==========================================
        $is_ordered_slug = sanitize_text_field($_GET[$slug . '_ordered'] ?? '');
        $is_ordered_raw  = sanitize_text_field($_GET['ordered'] ?? '');

        if ($is_ordered_slug === '1' || $is_ordered_raw === '1') {
            $gateway_type   = sanitize_text_field($_GET[$slug . '_gateway'] ?? $_GET['gateway'] ?? 'Stripe');
            $transaction_id = sanitize_text_field($_GET[$slug . '_session_id'] ?? $_GET[$slug . '_token'] ?? $_GET['session_id'] ?? $_GET['token'] ?? '');
            $transient_Key  = $slug . '_processed_' . ($transaction_id ?: md5($_GET[$slug . '_email'] ?? $_GET['email'] ?? 'order'));

            if (!get_transient($transient_Key)) {
                $name         = sanitize_text_field(urldecode($_GET[$slug . '_name'] ?? $_GET['name'] ?? 'Customer'));
                $email        = sanitize_email(urldecode($_GET[$slug . '_email'] ?? $_GET['email'] ?? ''));
                $qty          = intval($_GET[$slug . '_qty'] ?? $_GET['qty'] ?? 1);
                $mode         = sanitize_text_field($_GET[$slug . '_mode'] ?? $_GET['mode'] ?? 'multi');
                $is_lifetime  = !empty($_GET[$slug . '_lifetime'] ?? $_GET['lifetime']) ? true : false;
                $total_amount = floatval($_GET[$slug . '_amount'] ?? $_GET['amount'] ?? 0);

                if (!empty($email)) {
                    $user_auth = KeyVault_Manager_User_Auth::get_instance();
                    $db_user   = $user_auth->get_or_create_user($name, $email);
                    $user_id   = $db_user ? intval($db_user->id) : 0;

                    $generated_keys = KeyVault_Manager_DB::get_instance()->generate_license_keys(
                        $email,
                        $name,
                        $qty,
                        $mode,
                        $is_lifetime,
                        $gateway_type . ' Checkout Payment',
                        $user_id
                    );

                    $keys_string     = is_array($generated_keys) ? implode(', ', $generated_keys) : (string) $generated_keys;
                    $custom_order_id = $this->generate_sequential_order_id($slug);

                    global $wpdb;
                    $wpdb->insert($wpdb->prefix . $slug . '_orders', array(
                        'order_id'       => $custom_order_id,
                        'customer_name'  => $name,
                        'customer_email' => $email,
                        'amount'         => $total_amount,
                        'gateway'        => $gateway_type,
                        'quantity'       => $qty,
                        'license_mode'   => $mode,
                        'license_keys'   => $keys_string,
                        'status'         => 'completed',
                        'created_at'     => current_time('mysql'),
                    ));

                    set_transient($transient_Key, true, DAY_IN_SECONDS);
                }
            }
        }

        // ==========================================
        // 3. Login Submission
        // ==========================================
        if (isset($_POST[$slug . '_submit_login']) && check_admin_referer($slug . '_login_action', $slug . '_login_nonce')) {
            if (KeyVault_Manager_User_Auth::get_instance()->authenticate(sanitize_email($_POST[$slug . '_login_email']), $_POST[$slug . '_login_pass'])) {
                wp_safe_redirect($account_url);
                exit;
            }
            wp_safe_redirect($this->get_url(array('err' => 'invalid_login')));
            exit;
        }
    }

    /**
     * Helper to generate sequential order IDs (e.g., CDF-JFHV001 up to 999, then CDF-OPFH1000+)
     */
    private function generate_sequential_order_id($slug) {
        global $wpdb;
        $table_orders = $wpdb->prefix . $slug . '_orders';
        $prefix = strtoupper($slug);
        
        $last_order = $wpdb->get_var("SELECT order_id FROM {$table_orders} WHERE order_id LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1");
        
        $random_hex = strtoupper(wp_generate_password(4, false, false));
        $next_seq = 1;

        if ($last_order && preg_match('/-([A-Z0-9]{4})(\d+)$/', $last_order, $matches)) {
            $random_hex = $matches[1];
            $next_seq = intval($matches[2]) + 1;
        }

        $seq_formatted = ($next_seq <= 999) ? str_pad($next_seq, 3, '0', STR_PAD_LEFT) : (string)$next_seq;

        return $prefix . '-' . $random_hex . $seq_formatted;
    }

    public function handle_unlink_action() {
        $slug = KVM_SLUG;
        if (isset($_GET[$slug . '_action']) && $_GET[$slug . '_action'] === 'unlink_user' && isset($_GET['act_id'])) {
            $act_id = intval($_GET['act_id']);
            if (wp_verify_nonce($_GET['_wpnonce'] ?? '', $slug . '_unlink_user_' . $act_id)) {
                global $wpdb;
                $act = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$slug}_activations WHERE id = %d", $act_id));
                if ($act) {
                    $wpdb->delete($wpdb->prefix . $slug . '_activations', array('id' => $act_id));
                }
                wp_safe_redirect($this->get_url(array('tab' => 'licenses')));
                exit;
            }
        }
    }
}
KeyVault_Manager_Public::get_instance();