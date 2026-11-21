<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Emails {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('kvm_daily_maintenance', array($this, 'run_maintenance'));
    }

    /**
     * Backwards-compatible order email dispatcher.
     *
     * @param string $name
     * @param string $email
     * @param array|string $keys  All generated license keys (not just the first).
     * @param int    $quantity
     * @param string $temp_pass   One-time portal password (shown in email only).
     * @param bool   $is_lifetime
     * @param array  $order_ctx   array(order_id, amount, gateway)
     */
    public function send_order_emails($name, $email, $keys, $quantity, $temp_pass = '', $is_lifetime = false, $order_ctx = array()) {
        $billing_type = $is_lifetime ? 'Lifetime' : '1 Year (Annual)';
        $login_url    = KeyVault_Manager_Public::portal_url();

        $data = array(
            'customer_name'  => $name,
            'customer_email' => $email,
            'order_id'       => isset($order_ctx['order_id']) ? $order_ctx['order_id'] : '',
            'license_keys'   => implode("\n", (array) $keys),
            'quantity'       => intval($quantity),
            'billing_type'   => $billing_type,
            'temp_pass'      => $temp_pass,
            'amount'         => isset($order_ctx['amount']) ? (string) $order_ctx['amount'] : '',
            'gateway'        => isset($order_ctx['gateway']) ? (string) $order_ctx['gateway'] : '',
            'portal_url'     => $login_url,
        );

        $this->send_event_email('admin_new_order', '', $data);
        $this->send_event_email('user_order_delivery', $email, $data);

        if (!empty($temp_pass)) {
            $this->send_event_email('user_welcome_portal', $email, $data);
        }
    }

    /**
     * Dispatch a specific notification event configured in the Email tab.
     *
     * @param string $event_key Event key (e.g. admin_new_order, user_order_delivery).
     * @param string $to_email  Target email for user notifications (ignored for admin_* events).
     * @param array  $data      Associative array of merge tag values.
     * @return bool
     */
    public function send_event_email($event_key, $to_email = '', $data = array()) {
        $event_key = sanitize_key($event_key);
        $templates = get_option('kvm_email_templates', array());
        if (!is_array($templates)) {
            $templates = array();
        }

        $defaults = $this->get_default_event_config($event_key);
        if (empty($defaults)) {
            return false;
        }

        $cfg     = isset($templates[$event_key]) && is_array($templates[$event_key]) ? $templates[$event_key] : array();
        $enabled = isset($cfg['enabled']) ? (string) $cfg['enabled'] : (string) $defaults['default'];

        if ('1' !== $enabled) {
            return false;
        }

        $is_admin_event = (0 === strpos($event_key, 'admin_'));

        if ($is_admin_event) {
            $raw_recipients = !empty($cfg['recipient'])
                ? $cfg['recipient']
                : get_option('kvm_admin_email_recipients', get_option('admin_email'));

            $recipients = array_filter(array_map('sanitize_email', array_map('trim', explode(',', (string) $raw_recipients))));
            if (empty($recipients)) {
                return false;
            }
            $target = $recipients;
        } else {
            $clean_to = sanitize_email($to_email);
            if (empty($clean_to)) {
                return false;
            }
            $target = $clean_to;
        }

        $replacements = $this->build_replacements($data, is_string($target) ? $target : '');

        $raw_subject = !empty($cfg['subject']) ? $cfg['subject'] : $defaults['subject'];
        $raw_body    = !empty($cfg['body']) ? $cfg['body'] : $defaults['body'];

        $subject = strtr($raw_subject, $replacements);
        $body    = nl2br(esc_html(strtr($raw_body, $replacements)));

        $primary_color = sanitize_hex_color(get_option('kvm_primary_color', '#2563eb'));
        if (!$primary_color) {
            $primary_color = '#2563eb';
        }
        $html_body = $this->wrap_email_html($subject, $body, $primary_color, $data);

        $headers = $this->build_headers();

        return (bool) wp_mail($target, $subject, $html_body, $headers);
    }

    private function build_headers() {
        $headers   = array('Content-Type: text/html; charset=UTF-8');
        $from_name = sanitize_text_field(get_option('kvm_email_from_name', get_bloginfo('name')));
        $from_mail = sanitize_email(get_option('kvm_email_from_email', get_option('admin_email')));
        $reply_to  = sanitize_email(get_option('kvm_email_reply_to', get_option('admin_email')));

        if (!empty($from_mail) && !empty($from_name)) {
            $headers[] = 'From: ' . $from_name . ' <' . $from_mail . '>';
        } elseif (!empty($from_mail)) {
            $headers[] = 'From: ' . $from_mail;
        }

        if (!empty($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        return $headers;
    }

    private function build_replacements($data, $fallback_email = '') {
        $customer_email = !empty($data['customer_email']) ? sanitize_email($data['customer_email']) : sanitize_email($fallback_email);
        $customer_name  = !empty($data['customer_name']) ? sanitize_text_field($data['customer_name']) : 'Customer';
        $portal_url     = !empty($data['portal_url']) ? esc_url_raw($data['portal_url']) : esc_url_raw(KeyVault_Manager_Public::portal_url());

        $ip_address = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return array(
            '{customer_name}'  => $customer_name,
            '{customer_email}' => $customer_email,
            '{order_id}'       => isset($data['order_id']) ? sanitize_text_field($data['order_id']) : '',
            '{license_keys}'   => isset($data['license_keys']) ? sanitize_textarea_field($data['license_keys']) : '',
            '{quantity}'       => isset($data['quantity']) ? sanitize_text_field((string) $data['quantity']) : '',
            '{amount}'         => isset($data['amount']) ? sanitize_text_field((string) $data['amount']) : '',
            '{gateway}'        => isset($data['gateway']) ? sanitize_text_field((string) $data['gateway']) : '',
            '{domain}'         => isset($data['domain']) ? sanitize_text_field($data['domain']) : '',
            '{expiry_date}'    => isset($data['expiry_date']) ? sanitize_text_field($data['expiry_date']) : '',
            '{portal_url}'     => $portal_url,
            '{reset_link}'     => isset($data['reset_link']) ? esc_url_raw($data['reset_link']) : '',
            '{ip_address}'     => isset($data['ip_address']) ? sanitize_text_field($data['ip_address']) : $ip_address,
        );
    }

    private function wrap_email_html($title, $body_html, $primary_color, $data = array()) {
        $temp_pass_html = '';
        if (!empty($data['temp_pass'])) {
            $temp_pass_html = '<p><strong>Portal Password:</strong> <code>' . esc_html($data['temp_pass']) . '</code></p>';
        }

        return '<div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f8fafc;padding:30px;border-radius:14px;color:#1e293b;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;">' .
            '<h2 style="color:' . esc_attr($primary_color) . ';margin-top:0;font-size:20px;">' . esc_html($title) . '</h2>' .
            '<div style="background:#ffffff;border:1px solid #cbd5e1;padding:20px;border-radius:10px;line-height:1.6;">' .
            $body_html .
            $temp_pass_html .
            '</div></div>';
    }

    private function get_default_event_config($event_key) {
        $all = array(
            'admin_new_order' => array(
                'default' => get_option('kvm_admin_email_enabled', '1'),
                'subject' => 'New Order #{order_id} - {customer_name} ({amount})',
                'body'    => "A new order has been completed.\n\nOrder ID: {order_id}\nCustomer: {customer_name} ({customer_email})\nGateway: {gateway}\nAmount: {amount}\nQuantity: {quantity}\nLicense Keys: {license_keys}",
            ),
            'admin_payment_issue' => array(
                'default' => '1',
                'subject' => 'Payment Alert ({gateway}) - {customer_email}',
                'body'    => "A payment status alert occurred.\n\nCustomer: {customer_name} ({customer_email})\nGateway: {gateway}\nOrder ID: {order_id}\nAmount: {amount}",
            ),
            'admin_new_user' => array(
                'default' => '0',
                'subject' => 'New Customer Registered: {customer_name}',
                'body'    => "A new customer account has been created.\n\nName: {customer_name}\nEmail: {customer_email}\nOrder ID: {order_id}",
            ),
            'admin_site_activated' => array(
                'default' => '0',
                'subject' => 'License Activated on {domain} ({customer_email})',
                'body'    => "A license key was activated.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_name} ({customer_email})",
            ),
            'admin_site_unlinked' => array(
                'default' => '0',
                'subject' => 'Domain Unlinked: {domain}',
                'body'    => "A domain was unlinked from a license key.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_email}",
            ),
            'admin_invalid_attempt' => array(
                'default' => '0',
                'subject' => 'Failed License Check on {domain}',
                'body'    => "An invalid or rejected license check occurred.\n\nDomain: {domain}\nLicense Key: {license_keys}\nIP Address: {ip_address}",
            ),
            'user_order_delivery' => array(
                'default' => get_option('kvm_user_email_enabled', '1'),
                'subject' => 'Your License Keys & Order Receipt (#{order_id})',
                'body'    => "Hi {customer_name},\n\nThank you for your order (#{order_id}).\n\nLicense Key(s):\n{license_keys}\n\nQuantity: {quantity}\nTotal Paid: {amount}\nClient Portal: {portal_url}",
            ),
            'user_welcome_portal' => array(
                'default' => get_option('kvm_user_email_enabled', '1'),
                'subject' => 'Welcome to Your Client Portal',
                'body'    => "Hi {customer_name},\n\nYour client portal account is ready.\n\nLogin Email: {customer_email}\nPortal URL: {portal_url}",
            ),
            'user_password_reset' => array(
                'default' => '1',
                'subject' => 'Password Reset Request',
                'body'    => "Hi {customer_name},\n\nUse the link below to reset your password:\n{reset_link}\n\nIf you did not request this, ignore this email.",
            ),
            'user_password_changed' => array(
                'default' => '1',
                'subject' => 'Your Password Has Been Changed',
                'body'    => "Hi {customer_name},\n\nYour account password was recently updated. If this was not you, contact support immediately.",
            ),
            'user_domain_updated' => array(
                'default' => '0',
                'subject' => 'License Domain Update: {domain}',
                'body'    => "Hi {customer_name},\n\nYour license key domain list was updated for {domain}.\n\nManage licenses: {portal_url}",
            ),
            'user_renewal_reminder' => array(
                'default' => '1',
                'subject' => 'License Renewal Reminder ({expiry_date})',
                'body'    => "Hi {customer_name},\n\nYour license key ({license_keys}) will expire on {expiry_date}.\n\nRenew in your portal: {portal_url}",
            ),
            'user_license_status' => array(
                'default' => '1',
                'subject' => 'Important Notice About Your License Key',
                'body'    => "Hi {customer_name},\n\nYour license key ({license_keys}) status has been updated.\n\nCheck your portal for details: {portal_url}",
            ),
        );

        return isset($all[$event_key]) ? $all[$event_key] : array();
    }

    /**
     * Daily maintenance (kvm_daily_maintenance cron):
     * - logs are purged by the Logger;
     * - renewal reminders are dispatched once per license, 14 days before expiry.
     */
    public function run_maintenance() {
        global $wpdb;

        $table  = KeyVault_Manager_DB::table('licenses');
        $users  = KeyVault_Manager_DB::table('users');
        $cutoff = date('Y-m-d H:i:s', strtotime(current_time('mysql')) + 14 * DAY_IN_SECONDS);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.email AS portal_email, u.name AS portal_name
             FROM $table l
             LEFT JOIN $users u ON u.id = l.user_id
             WHERE l.status = 'active'
               AND l.expires_at IS NOT NULL AND l.expires_at <> ''
               AND l.expires_at <= %s",
            $cutoff
        ));

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $lic) {
            $email = !empty($lic->portal_email) ? $lic->portal_email : $lic->customer_email;
            $name  = !empty($lic->portal_name) ? $lic->portal_name : $lic->customer_name;
            if (empty($email)) {
                continue;
            }

            // One reminder per (license, expiry) pair — transient lives 30 days.
            $flag_key = 'kvm_reminded_' . intval($lic->id);
            $already  = get_transient($flag_key);
            if ($already && $already === (string) $lic->expires_at) {
                continue;
            }

            $this->send_event_email('user_renewal_reminder', $email, array(
                'customer_name' => $name,
                'customer_email' => $email,
                'license_keys'  => $lic->license_key,
                'expiry_date'   => date('M d, Y', strtotime($lic->expires_at)),
                'portal_url'    => KeyVault_Manager_Public::portal_url(),
            ));

            set_transient($flag_key, (string) $lic->expires_at, 30 * DAY_IN_SECONDS);
        }
    }
}
