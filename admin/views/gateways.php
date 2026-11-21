<?php
if (!defined('ABSPATH')) { exit; }

$env          = get_option('kvm_environment', 'sandbox');
$settings     = get_option('kvm_api_settings', array());
$enable_nonce = get_option('kvm_enable_nonce', 1);

$stripe_pub_test    = isset($settings['stripe_publishable_test']) ? $settings['stripe_publishable_test'] : '';
$stripe_sec_test    = isset($settings['stripe_secret_test']) ? $settings['stripe_secret_test'] : '';
$stripe_pub_live    = isset($settings['stripe_publishable_live']) ? $settings['stripe_publishable_live'] : '';
$stripe_sec_live    = isset($settings['stripe_secret_live']) ? $settings['stripe_secret_live'] : '';
$paypal_client_test = isset($settings['paypal_client_test']) ? $settings['paypal_client_test'] : '';
$paypal_sec_test    = isset($settings['paypal_secret_test']) ? $settings['paypal_secret_test'] : '';
$paypal_client_live = isset($settings['paypal_client_live']) ? $settings['paypal_client_live'] : '';
$paypal_sec_live    = isset($settings['paypal_secret_live']) ? $settings['paypal_secret_live'] : '';
$paypal_plan        = isset($settings['paypal_plan_id']) ? $settings['paypal_plan_id'] : '';

if (isset($_GET['saved'])) echo '<div class="kvm-notice success">✅ Gateway settings saved successfully!</div>';
?>

<div class="kvm-card-panel top-margin">
    <h3 class="kvm-section-heading">💳 Payment Gateway API Settings</h3>
    <p class="kvm-section-sub">Configure Stripe and PayPal payment gateway credentials, environment modes, and security options.</p>

    <form method="POST">
        <?php wp_nonce_field('kvm_gateway_settings_action', 'kvm_gateway_settings_nonce'); ?>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Environment Mode</th>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label class="kvm-switch" style="position: relative; display: inline-block; width: 50px; height: 24px; margin: 0;">
                            <input type="checkbox" id="kvm_env_switch" name="kvm_environment" value="live" <?php checked($env, 'live'); ?> style="opacity: 0; width: 0; height: 0;">
                            <span class="kvm-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
                        </label>
                        <div>
                            <strong id="kvm_env_label" style="font-size: 14px; color: #0f172a; display: block;"><?php echo ('live' === $env) ? 'Live / Production Mode' : 'Test / Sandbox Mode'; ?></strong>
                            <span style="font-size: 13px; color: #64748b;">Switch between Sandbox test credentials and Live production credentials seamlessly.</span>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">WordPress Nonce Security</th>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label class="kvm-switch" style="position: relative; display: inline-block; width: 50px; height: 24px; margin: 0;">
                            <input type="checkbox" name="kvm_enable_nonce" value="1" <?php checked($enable_nonce, 1); ?> style="opacity: 0; width: 0; height: 0;">
                            <span class="kvm-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
                        </label>
                        <div>
                            <strong style="font-size: 14px; color: #0f172a; display: block;">Enable Nonce Verification</strong>
                            <span style="font-size: 13px; color: #64748b;">Protects checkout form submissions against CSRF. (Default: ON)</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Sandbox Credentials Section -->
        <div id="kvm_test_creds" style="display: <?php echo ('live' === $env) ? 'none' : 'block'; ?>; margin-top: 20px;">
            <h4 style="margin-top: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; color: #2563eb; font-size: 16px;">Sandbox / Test API Credentials</h4>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Stripe Publishable Key</th>
                    <td>
                        <input type="text" name="kvm_stripe_publishable_test" value="<?php echo esc_attr($stripe_pub_test); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                        <p class="description">Starts with pk_test_</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Secret Key</th>
                    <td>
                        <input type="password" name="kvm_stripe_secret_test" value="<?php echo esc_attr($stripe_sec_test); ?>" class="regular-text kvm-input" autocomplete="new-password" style="width: 100%; max-width: 450px;">
                        <p class="description">Starts with sk_test_</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">PayPal Client ID</th>
                    <td>
                        <input type="text" name="kvm_paypal_client_test" value="<?php echo esc_attr($paypal_client_test); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                    </td>
                </tr>
                <tr>
                    <th scope="row">PayPal Secret Key</th>
                    <td>
                        <input type="password" name="kvm_paypal_secret_test" value="<?php echo esc_attr($paypal_sec_test); ?>" class="regular-text kvm-input" autocomplete="new-password" style="width: 100%; max-width: 450px;">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Live Credentials Section -->
        <div id="kvm_live_creds" style="display: <?php echo ('live' === $env) ? 'block' : 'none'; ?>; margin-top: 20px;">
            <h4 style="margin-top: 25px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; color: #16a34a; font-size: 16px;">Live / Production API Credentials</h4>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Stripe Publishable Key</th>
                    <td>
                        <input type="text" name="kvm_stripe_publishable_live" value="<?php echo esc_attr($stripe_pub_live); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                        <p class="description">Starts with pk_live_</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stripe Secret Key</th>
                    <td>
                        <input type="password" name="kvm_stripe_secret_live" value="<?php echo esc_attr($stripe_sec_live); ?>" class="regular-text kvm-input" autocomplete="new-password" style="width: 100%; max-width: 450px;">
                        <p class="description">Starts with sk_live_</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">PayPal Client ID</th>
                    <td>
                        <input type="text" name="kvm_paypal_client_live" value="<?php echo esc_attr($paypal_client_live); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                    </td>
                </tr>
                <tr>
                    <th scope="row">PayPal Secret Key</th>
                    <td>
                        <input type="password" name="kvm_paypal_secret_live" value="<?php echo esc_attr($paypal_sec_live); ?>" class="regular-text kvm-input" autocomplete="new-password" style="width: 100%; max-width: 450px;">
                    </td>
                </tr>
            </table>
        </div>

        <h4 style="margin-top: 30px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 16px;">General Configuration</h4>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">PayPal Monthly Plan ID</th>
                <td>
                    <input type="text" name="kvm_paypal_plan_id" value="<?php echo esc_attr($paypal_plan); ?>" class="regular-text kvm-input" style="width: 100%; max-width: 450px;">
                    <p class="description">Enter your PayPal Billing Agreement Plan ID for monthly recurring subscriptions (starts with P-...).</p>
                </td>
            </tr>
        </table>

        <div style="margin-top: 30px;">
            <button type="submit" name="kvm_save_gateway_settings" class="kvm-btn-submit">Save Gateway Settings</button>
        </div>
    </form>
</div>

<style>
.kvm-switch input { opacity: 0; width: 0; height: 0; }
.kvm-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px; }
.kvm-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
.kvm-switch input:checked + .kvm-slider { background-color: #2563eb; }
.kvm-switch input:checked + .kvm-slider:before { transform: translateX(26px); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const envSwitch = document.getElementById('kvm_env_switch');
    const envLabel  = document.getElementById('kvm_env_label');
    const testCreds = document.getElementById('kvm_test_creds');
    const liveCreds = document.getElementById('kvm_live_creds');

    if (envSwitch && envLabel) {
        envSwitch.addEventListener('change', function() {
            if (this.checked) {
                envLabel.textContent = 'Live / Production Mode';
                if(testCreds) testCreds.style.display = 'none';
                if(liveCreds) liveCreds.style.display = 'block';
            } else {
                envLabel.textContent = 'Test / Sandbox Mode';
                if(testCreds) testCreds.style.display = 'block';
                if(liveCreds) liveCreds.style.display = 'none';
            }
        });
    }
});
</script>