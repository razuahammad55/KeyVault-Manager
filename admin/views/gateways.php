<?php
if (!defined('ABSPATH')) { exit; }

$env          = get_option('kvm_environment', 'sandbox');
$settings     = get_option('kvm_api_settings', array());
$enable_nonce = get_option('kvm_enable_nonce', 1);

$stripe_pub_test    = isset($settings['stripe_publishable_test']) ? $settings['stripe_publishable_test'] : '';
$stripe_sec_test    = isset($settings['stripe_secret_test']) ? $settings['stripe_secret_test'] : '';
$stripe_pub_live    = isset($settings['stripe_publishable_live']) ? $settings['stripe_publishable_live'] : '';
$stripe_sec_live    = isset($settings['stripe_secret_live']) ? $settings['stripe_secret_live'] : '';
$stripe_webhook     = isset($settings['stripe_webhook_secret']) ? $settings['stripe_webhook_secret'] : '';
$paypal_client_test = isset($settings['paypal_client_test']) ? $settings['paypal_client_test'] : '';
$paypal_sec_test    = isset($settings['paypal_secret_test']) ? $settings['paypal_secret_test'] : '';
$paypal_client_live = isset($settings['paypal_client_live']) ? $settings['paypal_client_live'] : '';
$paypal_sec_live    = isset($settings['paypal_secret_live']) ? $settings['paypal_secret_live'] : '';
$paypal_plan        = isset($settings['paypal_plan_id']) ? $settings['paypal_plan_id'] : '';

$webhook_url = site_url('/wp-json/' . (defined('KVM_REST_NAMESPACE') ? KVM_REST_NAMESPACE : 'kvm/v1') . '/webhook/stripe');

if (isset($_GET['saved'])) echo '<div class="kvm-notice success">✅ Gateway settings saved successfully!</div>';
?>

<div class="kvm-card-panel">
    <h3 class="kvm-section-heading">💳 Payment Gateway API Settings</h3>
    <p class="kvm-section-sub">Configure Stripe and PayPal credentials, environment modes, and security options.</p>

    <form method="POST">
        <?php wp_nonce_field('kvm_gateway_settings_action', 'kvm_gateway_settings_nonce'); ?>

        <div class="kvm-settings-grid">
            <div class="kvm-form-group">
                <label class="kvm-form-label">Environment Mode</label>
                <div class="kvm-switch-row">
                    <label class="kvm-switch">
                        <input type="checkbox" id="kvm_env_switch" name="kvm_environment" value="live" <?php checked($env, 'live'); ?>>
                        <span class="kvm-slider" aria-hidden="true"></span>
                    </label>
                    <div>
                        <strong id="kvm_env_label"><?php echo ('live' === $env) ? 'Live / Production Mode' : 'Test / Sandbox Mode'; ?></strong>
                        <small class="kvm-helper-text">Switch between sandbox test credentials and live production credentials.</small>
                    </div>
                </div>
            </div>

            <div class="kvm-form-group">
                <label class="kvm-form-label">WordPress Nonce Security</label>
                <div class="kvm-switch-row">
                    <label class="kvm-switch">
                        <input type="checkbox" name="kvm_enable_nonce" value="1" <?php checked($enable_nonce, 1); ?>>
                        <span class="kvm-slider" aria-hidden="true"></span>
                    </label>
                    <div>
                        <strong>Enable Nonce Verification</strong>
                        <small class="kvm-helper-text">Protects checkout form submissions against CSRF (default ON; login forms are always protected).</small>
                    </div>
                </div>
            </div>
        </div>

        <div id="kvm_test_creds" class="kvm-creds-section" style="display: <?php echo ('live' === $env) ? 'none' : 'block'; ?>;">
            <h4 class="kvm-group-heading accent-blue">Sandbox / Test API Credentials</h4>
            <div class="kvm-settings-grid">
                <div class="kvm-form-group">
                    <label class="kvm-form-label">Stripe Publishable Key</label>
                    <input type="text" name="kvm_stripe_publishable_test" value="<?php echo esc_attr($stripe_pub_test); ?>" class="kvm-input" autocomplete="off">
                    <small class="kvm-helper-text">Starts with pk_test_</small>
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">Stripe Secret Key</label>
                    <input type="password" name="kvm_stripe_secret_test" value="<?php echo esc_attr($stripe_sec_test); ?>" class="kvm-input" autocomplete="new-password">
                    <small class="kvm-helper-text">Starts with sk_test_</small>
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">PayPal Client ID</label>
                    <input type="text" name="kvm_paypal_client_test" value="<?php echo esc_attr($paypal_client_test); ?>" class="kvm-input" autocomplete="off">
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">PayPal Secret Key</label>
                    <input type="password" name="kvm_paypal_secret_test" value="<?php echo esc_attr($paypal_sec_test); ?>" class="kvm-input" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div id="kvm_live_creds" class="kvm-creds-section" style="display: <?php echo ('live' === $env) ? 'block' : 'none'; ?>;">
            <h4 class="kvm-group-heading accent-green">Live / Production API Credentials</h4>
            <div class="kvm-settings-grid">
                <div class="kvm-form-group">
                    <label class="kvm-form-label">Stripe Publishable Key</label>
                    <input type="text" name="kvm_stripe_publishable_live" value="<?php echo esc_attr($stripe_pub_live); ?>" class="kvm-input" autocomplete="off">
                    <small class="kvm-helper-text">Starts with pk_live_</small>
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">Stripe Secret Key</label>
                    <input type="password" name="kvm_stripe_secret_live" value="<?php echo esc_attr($stripe_sec_live); ?>" class="kvm-input" autocomplete="new-password">
                    <small class="kvm-helper-text">Starts with sk_live_</small>
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">PayPal Client ID</label>
                    <input type="text" name="kvm_paypal_client_live" value="<?php echo esc_attr($paypal_client_live); ?>" class="kvm-input" autocomplete="off">
                </div>
                <div class="kvm-form-group">
                    <label class="kvm-form-label">PayPal Secret Key</label>
                    <input type="password" name="kvm_paypal_secret_live" value="<?php echo esc_attr($paypal_sec_live); ?>" class="kvm-input" autocomplete="new-password">
                </div>
            </div>
        </div>

        <h4 class="kvm-group-heading">Stripe Webhook &amp; PayPal Plans</h4>
        <div class="kvm-settings-grid">
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_stripe_webhook_secret">Stripe Webhook Signing Secret</label>
                <input type="password" id="kvm_stripe_webhook_secret" name="kvm_stripe_webhook_secret" value="<?php echo esc_attr($stripe_webhook); ?>" class="kvm-input" autocomplete="new-password">
                <small class="kvm-helper-text">Required: webhook events without a valid signature are rejected (whsec_… from your Stripe dashboard).</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_paypal_plan_id">PayPal Billing Plan ID (optional)</label>
                <input type="text" id="kvm_paypal_plan_id" name="kvm_paypal_plan_id" value="<?php echo esc_attr($paypal_plan); ?>" class="kvm-input" autocomplete="off">
                <small class="kvm-helper-text">PayPal Billing Agreement Plan ID for monthly recurring subscriptions (starts with P-…).</small>
            </div>
        </div>

        <div class="kvm-panel kvm-panel--soft">
            <strong>Webhook Endpoint URL</strong>
            <p class="kvm-muted">Point your Stripe webhook endpoint at:</p>
            <code class="kvm-code kvm-webhook-url"><?php echo esc_html($webhook_url); ?></code>
        </div>

        <div class="kvm-form-actions">
            <button type="submit" name="kvm_save_gateway_settings" class="kvm-btn kvm-btn--primary">Save Gateway Settings</button>
        </div>
    </form>
</div>
