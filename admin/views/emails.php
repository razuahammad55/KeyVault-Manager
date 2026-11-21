<?php
if (!defined('ABSPATH')) { exit; }

$from_name        = get_option('kvm_email_from_name', get_bloginfo('name'));
$from_email       = get_option('kvm_email_from_email', get_option('admin_email'));
$reply_to         = get_option('kvm_email_reply_to', get_option('admin_email'));
$admin_recipients = get_option('kvm_admin_email_recipients', get_option('admin_email'));

$templates = get_option('kvm_email_templates', array());
if (!is_array($templates)) {
    $templates = array();
}

$default_admin_enabled = get_option('kvm_admin_email_enabled', '1');
$default_user_enabled  = get_option('kvm_user_email_enabled', '1');

$admin_events = array(
    'admin_new_order' => array(
        'title'   => 'New Order Completed',
        'desc'    => 'Sent when a Stripe or PayPal checkout succeeds and generates order/license records.',
        'default' => $default_admin_enabled,
        'subject' => 'New Order #{order_id} - {customer_name} ({amount})',
        'body'    => "A new order has been completed.\n\nOrder ID: {order_id}\nCustomer: {customer_name} ({customer_email})\nGateway: {gateway}\nAmount: {amount}\nQuantity: {quantity}\nLicense Keys: {license_keys}",
    ),
    'admin_payment_issue' => array(
        'title'   => 'Payment Failed / Cancelled',
        'desc'    => 'Sent when a customer abandons or cancels a checkout session.',
        'default' => '1',
        'subject' => 'Payment Alert ({gateway}) - {customer_email}',
        'body'    => "A payment status alert occurred.\n\nCustomer: {customer_name} ({customer_email})\nGateway: {gateway}\nOrder ID: {order_id}\nAmount: {amount}",
    ),
    'admin_new_user' => array(
        'title'   => 'New Customer Account Created',
        'desc'    => 'Sent when a new client portal user account is created during checkout or registration.',
        'default' => '0',
        'subject' => 'New Customer Registered: {customer_name}',
        'body'    => "A new customer account has been created.\n\nName: {customer_name}\nEmail: {customer_email}\nOrder ID: {order_id}",
    ),
    'admin_site_activated' => array(
        'title'   => 'Domain Activated',
        'desc'    => 'Sent when a license key is activated on a website (REST /activate).',
        'default' => '0',
        'subject' => 'License Activated on {domain} ({customer_email})',
        'body'    => "A license key was activated.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_name} ({customer_email})",
    ),
    'admin_site_unlinked' => array(
        'title'   => 'Domain Deactivated / Unlinked',
        'desc'    => 'Sent when a connected website is removed or unlinked from a license key (REST /deactivate or portal unlink).',
        'default' => '0',
        'subject' => 'Domain Unlinked: {domain}',
        'body'    => "A domain was unlinked from a license key.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_email}",
    ),
    'admin_invalid_attempt' => array(
        'title'   => 'Invalid / Blocked License Attempt',
        'desc'    => 'Sent when a validation or activation request fails for an invalid, expired, or revoked key (rate-limited to 5 alerts per hour per IP).',
        'default' => '0',
        'subject' => 'Failed License Check on {domain}',
        'body'    => "An invalid or rejected license check occurred.\n\nDomain: {domain}\nLicense Key: {license_keys}\nIP Address: {ip_address}",
    ),
);

$user_events = array(
    'user_order_delivery' => array(
        'title'   => 'Order Receipt & License Key Delivery',
        'desc'    => 'Sent to the buyer after payment completes with order details and all license key(s).',
        'default' => $default_user_enabled,
        'subject' => 'Your License Keys & Order Receipt (#{order_id})',
        'body'    => "Hi {customer_name},\n\nThank you for your order (#{order_id}).\n\nLicense Key(s):\n{license_keys}\n\nQuantity: {quantity}\nTotal Paid: {amount}\nClient Portal: {portal_url}",
    ),
    'user_welcome_portal' => array(
        'title'   => 'Portal Welcome & Login Details',
        'desc'    => 'Sent when a new customer portal account is created for the buyer (also used for “Email New Password”).',
        'default' => $default_user_enabled,
        'subject' => 'Welcome to Your Client Portal',
        'body'    => "Hi {customer_name},\n\nYour client portal account is ready.\n\nLogin Email: {customer_email}\nPortal URL: {portal_url}",
    ),
    'user_password_reset' => array(
        'title'   => 'Password Reset Request',
        'desc'    => 'Sent when a user requests a password reset link from the portal recovery screen.',
        'default' => '1',
        'subject' => 'Password Reset Request',
        'body'    => "Hi {customer_name},\n\nUse the link below to reset your password:\n{reset_link}\n\nIf you did not request this, ignore this email.",
    ),
    'user_password_changed' => array(
        'title'   => 'Password Changed Confirmation',
        'desc'    => 'Sent after a user completes a password reset or updates their account password.',
        'default' => '1',
        'subject' => 'Your Password Has Been Changed',
        'body'    => "Hi {customer_name},\n\nYour account password was recently updated. If this was not you, contact support immediately.",
    ),
    'user_domain_updated' => array(
        'title'   => 'Site Activated / Unlinked Confirmation',
        'desc'    => 'Sent when a domain is activated on or removed from the customer’s license key.',
        'default' => '0',
        'subject' => 'License Domain Update: {domain}',
        'body'    => "Hi {customer_name},\n\nYour license key domain list was updated for {domain}.\n\nManage licenses: {portal_url}",
    ),
    'user_renewal_reminder' => array(
        'title'   => 'License Expiration / Renewal Reminder',
        'desc'    => 'Sent automatically (daily cron) 14 days before a non-lifetime license expires.',
        'default' => '1',
        'subject' => 'License Renewal Reminder ({expiry_date})',
        'body'    => "Hi {customer_name},\n\nYour license key ({license_keys}) will expire on {expiry_date}.\n\nRenew in your portal: {portal_url}",
    ),
    'user_license_status' => array(
        'title'   => 'License Revoked / Suspended Notice',
        'desc'    => 'Sent when an administrator revokes or re-activates a customer license key from the License Hub.',
        'default' => '1',
        'subject' => 'Important Notice About Your License Key',
        'body'    => "Hi {customer_name},\n\nYour license key ({license_keys}) status has been updated.\n\nCheck your portal for details: {portal_url}",
    ),
);

$merge_tags = array(
    '{customer_name}',
    '{customer_email}',
    '{order_id}',
    '{license_keys}',
    '{quantity}',
    '{amount}',
    '{gateway}',
    '{domain}',
    '{expiry_date}',
    '{portal_url}',
    '{reset_link}',
    '{ip_address}',
);

if (isset($_GET['saved'])) {
    echo '<div class="kvm-notice success">✅ Email settings saved successfully!</div>';
}
?>

<div class="kvm-card-panel">
    <h3 class="kvm-section-heading">✉️ Email Notification Controls</h3>
    <p class="kvm-section-sub">Configure global sender headers, admin alert situations, and customer lifecycle emails.</p>

    <form method="post">
        <?php wp_nonce_field('kvm_email_settings_action', 'kvm_email_settings_nonce'); ?>

        <h4 class="kvm-group-heading">Global Sender &amp; Admin Recipients</h4>
        <div class="kvm-settings-grid">
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_email_from_name">From Name</label>
                <input type="text" id="kvm_email_from_name" name="kvm_email_from_name" value="<?php echo esc_attr($from_name); ?>" class="kvm-input">
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_email_from_email">From Email</label>
                <input type="email" id="kvm_email_from_email" name="kvm_email_from_email" value="<?php echo esc_attr($from_email); ?>" class="kvm-input">
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_email_reply_to">Reply-To Email</label>
                <input type="email" id="kvm_email_reply_to" name="kvm_email_reply_to" value="<?php echo esc_attr($reply_to); ?>" class="kvm-input">
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_admin_email_recipients">Default Admin Recipients</label>
                <input type="text" id="kvm_admin_email_recipients" name="kvm_admin_email_recipients" value="<?php echo esc_attr($admin_recipients); ?>" class="kvm-input">
                <small class="kvm-helper-text">Comma-separated addresses for admin notifications unless overridden per situation below.</small>
            </div>
        </div>

        <div class="kvm-panel kvm-panel--soft">
            <strong class="kvm-merge-title">Available Merge Tags (click to insert):</strong>
            <div class="kvm-merge-tags">
                <?php foreach ($merge_tags as $tag) : ?>
                    <button type="button" class="button button-small kvm-merge-tag-btn" data-tag="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <h4 class="kvm-group-heading">Admin Notification Situations</h4>
        <?php foreach ($admin_events as $key => $meta) :
            $cfg       = isset($templates[$key]) ? $templates[$key] : array();
            $enabled   = isset($cfg['enabled']) ? (string) $cfg['enabled'] : (string) $meta['default'];
            $recipient = isset($cfg['recipient']) ? $cfg['recipient'] : '';
            $subject   = isset($cfg['subject']) ? $cfg['subject'] : $meta['subject'];
            $body      = isset($cfg['body']) ? $cfg['body'] : $meta['body'];
            ?>
            <details class="kvm-accordion">
                <summary>
                    <span><?php echo esc_html($meta['title']); ?></span>
                    <span class="kvm-accordion-state <?php echo ('1' === $enabled) ? 'on' : 'off'; ?>"><?php echo ('1' === $enabled) ? 'ENABLED' : 'DISABLED'; ?></span>
                </summary>
                <div class="kvm-accordion-body">
                    <p class="kvm-section-sub"><?php echo esc_html($meta['desc']); ?></p>
                    <div class="kvm-settings-grid">
                        <div class="kvm-form-group">
                            <label class="kvm-checkbox-label">
                                <input type="checkbox" name="kvm_email_templates[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, '1'); ?> class="kvm-checkbox">
                                Enable this admin email
                            </label>
                        </div>
                        <div class="kvm-form-group">
                            <label class="kvm-form-label">Recipient Override</label>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][recipient]" value="<?php echo esc_attr($recipient); ?>" placeholder="<?php echo esc_attr($admin_recipients); ?>" class="kvm-input">
                            <small class="kvm-helper-text">Leave blank to use Default Admin Recipients.</small>
                        </div>
                        <div class="kvm-form-group">
                            <label class="kvm-form-label">Subject Line</label>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($subject); ?>" class="kvm-input kvm-email-target">
                        </div>
                        <div class="kvm-form-group">
                            <label class="kvm-form-label">Message Body</label>
                            <textarea name="kvm_email_templates[<?php echo esc_attr($key); ?>][body]" rows="5" class="kvm-textarea kvm-email-target"><?php echo esc_textarea($body); ?></textarea>
                        </div>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>

        <h4 class="kvm-group-heading">User Notification Situations</h4>
        <?php foreach ($user_events as $key => $meta) :
            $cfg     = isset($templates[$key]) ? $templates[$key] : array();
            $enabled = isset($cfg['enabled']) ? (string) $cfg['enabled'] : (string) $meta['default'];
            $subject = isset($cfg['subject']) ? $cfg['subject'] : $meta['subject'];
            $body    = isset($cfg['body']) ? $cfg['body'] : $meta['body'];
            ?>
            <details class="kvm-accordion">
                <summary>
                    <span><?php echo esc_html($meta['title']); ?></span>
                    <span class="kvm-accordion-state <?php echo ('1' === $enabled) ? 'on' : 'off'; ?>"><?php echo ('1' === $enabled) ? 'ENABLED' : 'DISABLED'; ?></span>
                </summary>
                <div class="kvm-accordion-body">
                    <p class="kvm-section-sub"><?php echo esc_html($meta['desc']); ?></p>
                    <div class="kvm-settings-grid">
                        <div class="kvm-form-group">
                            <label class="kvm-checkbox-label">
                                <input type="checkbox" name="kvm_email_templates[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, '1'); ?> class="kvm-checkbox">
                                Enable this customer email
                            </label>
                        </div>
                        <div class="kvm-form-group">
                            <label class="kvm-form-label">Subject Line</label>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($subject); ?>" class="kvm-input kvm-email-target">
                        </div>
                        <div class="kvm-form-group">
                            <label class="kvm-form-label">Message Body</label>
                            <textarea name="kvm_email_templates[<?php echo esc_attr($key); ?>][body]" rows="5" class="kvm-textarea kvm-email-target"><?php echo esc_textarea($body); ?></textarea>
                        </div>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="kvm-form-actions">
            <button type="submit" name="kvm_save_email_settings" class="kvm-btn kvm-btn--primary">Save Email Settings</button>
        </div>
    </form>
</div>
