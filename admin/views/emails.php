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
        'title'   => 'Payment Failed / Cancelled / Refunded',
        'desc'    => 'Sent when a checkout is cancelled, payment fails, or a charge is refunded.',
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
        'title'   => 'Domain Activation / Slot Limit Reached',
        'desc'    => 'Sent when a license key is activated on a website or reaches maximum slot capacity.',
        'default' => '0',
        'subject' => 'License Activated on {domain} ({customer_email})',
        'body'    => "A license key was activated.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_name} ({customer_email})",
    ),
    'admin_site_unlinked' => array(
        'title'   => 'Domain Deactivated / Unlinked',
        'desc'    => 'Sent when a connected website is removed or unlinked from a license key.',
        'default' => '0',
        'subject' => 'Domain Unlinked: {domain}',
        'body'    => "A domain was unlinked from a license key.\n\nDomain: {domain}\nLicense Key: {license_keys}\nCustomer: {customer_email}",
    ),
    'admin_invalid_attempt' => array(
        'title'   => 'Invalid / Blocked License Attempt',
        'desc'    => 'Sent when a validation or activation request fails for an invalid, expired, or revoked key.',
        'default' => '0',
        'subject' => 'Failed License Check on {domain}',
        'body'    => "An invalid or rejected license check occurred.\n\nDomain: {domain}\nLicense Key: {license_keys}\nIP Address: {ip_address}",
    ),
);

$user_events = array(
    'user_order_delivery' => array(
        'title'   => 'Order Receipt & License Key Delivery',
        'desc'    => 'Sent to the buyer after payment completes with order details and license key(s).',
        'default' => $default_user_enabled,
        'subject' => 'Your License Keys & Order Receipt (#{order_id})',
        'body'    => "Hi {customer_name},\n\nThank you for your order (#{order_id}).\n\nLicense Key(s):\n{license_keys}\n\nQuantity: {quantity}\nTotal Paid: {amount}\nClient Portal: {portal_url}",
    ),
    'user_welcome_portal' => array(
        'title'   => 'Portal Welcome & Login Details',
        'desc'    => 'Sent when a new customer portal account is created for the buyer.',
        'default' => $default_user_enabled,
        'subject' => 'Welcome to Your Client Portal',
        'body'    => "Hi {customer_name},\n\nYour client portal account is ready.\n\nLogin Email: {customer_email}\nPortal URL: {portal_url}",
    ),
    'user_password_reset' => array(
        'title'   => 'Password Reset Request',
        'desc'    => 'Sent when a user requests a password reset link from the login or recovery screen.',
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
        'desc'    => 'Sent before a non-lifetime license expires so customers can renew without interruption.',
        'default' => '1',
        'subject' => 'License Renewal Reminder ({expiry_date})',
        'body'    => "Hi {customer_name},\n\nYour license key ({license_keys}) will expire on {expiry_date}.\n\nRenew in your portal: {portal_url}",
    ),
    'user_license_status' => array(
        'title'   => 'License Revoked / Suspended Notice',
        'desc'    => 'Sent when an administrator revokes or suspends a customer license key.',
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
);

if (isset($_GET['saved'])) {
    echo '<div class="kvm-notice success">✅ Email Settings Saved Successfully!</div>';
}
?>

<div class="kvm-card-panel top-margin">
    <h3 class="kvm-section-heading">✉️ Email Notification Controls</h3>
    <p class="kvm-section-sub">Configure global sender headers, admin alert situations, and customer lifecycle emails.</p>

    <form method="post">
        <?php wp_nonce_field('kvm_all_settings_action', 'kvm_all_settings_nonce'); ?>

        <h4 style="margin-top: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Global Sender &amp; Admin Recipients</h4>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">From Name</th>
                <td>
                    <input type="text" name="kvm_email_from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                </td>
            </tr>
            <tr>
                <th scope="row">From Email</th>
                <td>
                    <input type="email" name="kvm_email_from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                </td>
            </tr>
            <tr>
                <th scope="row">Reply-To Email</th>
                <td>
                    <input type="email" name="kvm_email_reply_to" value="<?php echo esc_attr($reply_to); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                </td>
            </tr>
            <tr>
                <th scope="row">Default Admin Recipients</th>
                <td>
                    <input type="text" name="kvm_admin_email_recipients" value="<?php echo esc_attr($admin_recipients); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                    <p class="description">Comma-separated email addresses for admin notifications unless overridden per situation below.</p>
                </td>
            </tr>
        </table>

        <div style="margin: 20px 0; padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
            <strong style="display: block; margin-bottom: 8px; font-size: 13px;">Available Merge Tags (Click to copy):</strong>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                <?php foreach ($merge_tags as $tag) : ?>
                    <button type="button" class="button button-small kvm-merge-tag-btn" data-tag="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <h4 style="margin-top: 28px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Admin Notification Situations</h4>
        <?php foreach ($admin_events as $key => $meta) :
            $cfg       = $templates[$key] ?? array();
            $enabled   = isset($cfg['enabled']) ? (string) $cfg['enabled'] : (string) $meta['default'];
            $recipient = $cfg['recipient'] ?? '';
            $subject   = $cfg['subject'] ?? $meta['subject'];
            $body      = $cfg['body'] ?? $meta['body'];
            ?>
            <details style="margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; background: #ffffff;">
                <summary style="cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <span><?php echo esc_html($meta['title']); ?></span>
                    <span style="font-size: 12px; font-weight: 600; color: <?php echo ('1' === $enabled) ? '#16a34a' : '#64748b'; ?>;">
                        <?php echo ('1' === $enabled) ? 'ENABLED' : 'DISABLED'; ?>
                    </span>
                </summary>
                <p class="description" style="margin-top: 8px;"><?php echo esc_html($meta['desc']); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <label class="kvm-checkbox-label">
                                <input type="checkbox" name="kvm_email_templates[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, '1'); ?> class="kvm-checkbox"> Enable this admin email
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Recipient Override</th>
                        <td>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][recipient]" value="<?php echo esc_attr($recipient); ?>" placeholder="<?php echo esc_attr($admin_recipients); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                            <p class="description">Leave blank to use Default Admin Recipients above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Subject Line</th>
                        <td>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($subject); ?>" class="regular-text kvm-input kvm-email-target" style="width: 100%; max-width: 550px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message Body</th>
                        <td>
                            <textarea name="kvm_email_templates[<?php echo esc_attr($key); ?>][body]" rows="5" class="large-text kvm-email-target" style="width: 100%; max-width: 550px;"><?php echo esc_textarea($body); ?></textarea>
                        </td>
                    </tr>
                </table>
            </details>
        <?php endforeach; ?>

        <h4 style="margin-top: 28px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">User Notification Situations</h4>
        <?php foreach ($user_events as $key => $meta) :
            $cfg     = $templates[$key] ?? array();
            $enabled = isset($cfg['enabled']) ? (string) $cfg['enabled'] : (string) $meta['default'];
            $subject = $cfg['subject'] ?? $meta['subject'];
            $body    = $cfg['body'] ?? $meta['body'];
            ?>
            <details style="margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; background: #ffffff;">
                <summary style="cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <span><?php echo esc_html($meta['title']); ?></span>
                    <span style="font-size: 12px; font-weight: 600; color: <?php echo ('1' === $enabled) ? '#16a34a' : '#64748b'; ?>;">
                        <?php echo ('1' === $enabled) ? 'ENABLED' : 'DISABLED'; ?>
                    </span>
                </summary>
                <p class="description" style="margin-top: 8px;"><?php echo esc_html($meta['desc']); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <label class="kvm-checkbox-label">
                                <input type="checkbox" name="kvm_email_templates[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($enabled, '1'); ?> class="kvm-checkbox"> Enable this customer email
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Subject Line</th>
                        <td>
                            <input type="text" name="kvm_email_templates[<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($subject); ?>" class="regular-text kvm-input kvm-email-target" style="width: 100%; max-width: 550px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message Body</th>
                        <td>
                            <textarea name="kvm_email_templates[<?php echo esc_attr($key); ?>][body]" rows="5" class="large-text kvm-email-target" style="width: 100%; max-width: 550px;"><?php echo esc_textarea($body); ?></textarea>
                        </td>
                    </tr>
                </table>
            </details>
        <?php endforeach; ?>

        <div style="margin-top: 24px;">
            <button type="submit" name="kvm_save_all_settings" class="kvm-btn-submit">Save Email Settings</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastFocusedField = null;

    document.querySelectorAll('.kvm-email-target').forEach(function(field) {
        field.addEventListener('focus', function() {
            lastFocusedField = this;
        });
    });

    document.querySelectorAll('.kvm-merge-tag-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tag = this.getAttribute('data-tag') || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(tag).catch(function() {});
            }
            if (lastFocusedField) {
                const start = lastFocusedField.selectionStart || lastFocusedField.value.length;
                const end = lastFocusedField.selectionEnd || lastFocusedField.value.length;
                const val = lastFocusedField.value;
                lastFocusedField.value = val.slice(0, start) + tag + val.slice(end);
                lastFocusedField.focus();
                lastFocusedField.selectionStart = lastFocusedField.selectionEnd = start + tag.length;
            }
        });
    });
});
</script>