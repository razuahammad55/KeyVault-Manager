<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;

// Retrieve all options safely with slug prefix
$zip_path              = get_option($slug . '_zip_file_path', '');
$primary_color         = get_option($slug . '_primary_color', '#2563eb');
$grace_days            = get_option($slug . '_grace_period_days', '7');
$login_url             = get_option($slug . '_custom_login_url', site_url('/' . $slug . '-account'));

// Pricing & Dynamic Tier Limits
$price_starter         = get_option($slug . '_price_starter', '49');
$starter_max           = get_option($slug . '_starter_max', '9');

$price_pro             = get_option($slug . '_price_professional', '39');
$pro_max               = get_option($slug . '_pro_max', '99');

$price_ent             = get_option($slug . '_price_enterprise', '30');

$delete_on_uninstall   = get_option($slug . '_delete_data_on_uninstall', 0);

if (isset($_GET['saved'])) echo '<div class="kvm-notice success">✅ Settings Saved Successfully!</div>';
?>

<div class="kvm-card-panel top-margin">
    <h3 class="kvm-section-heading">⚡ Shortcodes Reference</h3>
    <p class="kvm-section-sub">Copy and paste these shortcodes onto any WordPress page to render front-end user forms or customer dashboards.</p>

    <div class="kvm-shortcodes-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="kvm-shortcode-card">
            <strong class="kvm-shortcode-title">Account & Client Dashboard</strong>
            <code class="kvm-shortcode-code" tabindex="0">[<?php echo esc_html($slug); ?>_client_dashboard]</code>
            <p class="kvm-shortcode-desc">Renders the secure client account portal and license key management.</p>
        </div>
        <div class="kvm-shortcode-card">
            <strong class="kvm-shortcode-title">Order / Checkout Form</strong>
            <code class="kvm-shortcode-code" tabindex="0">[<?php echo esc_html($slug); ?>_order_form]</code>
            <p class="kvm-shortcode-desc">Displays the frontend order form to purchase or issue new license keys.</p>
        </div>
    </div>
</div>

<div class="kvm-card-panel">
    <h3>KeyVault Engine & Dynamic Pricing Tiers</h3>
    <form method="post">
        <?php wp_nonce_field($slug . '_all_settings_action', $slug . '_all_settings_nonce'); ?>
        
        <h4 class="kvm-group-heading">💰 Plan Pricing & Slot Range Configuration</h4>
        <div class="kvm-pricing-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            
            <!-- Starter Plan -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h5 style="margin: 0 0 12px 0; color: #0f172a; font-size: 15px;">Starter Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / yr):</label>
                <input type="number" step="0.01" name="<?php echo esc_attr($slug); ?>_price_starter" value="<?php echo esc_attr($price_starter); ?>" class="kvm-input" required style="margin-bottom: 12px;">
                
                <label class="kvm-form-label">Max Website Slots:</label>
                <input type="number" id="kvm_starter_max_input" name="<?php echo esc_attr($slug); ?>_starter_max" value="<?php echo esc_attr($starter_max); ?>" class="kvm-input" min="1" required>
                <small class="kvm-helper-text" style="display:block; margin-top:4px;">Range: 1 to <span id="kvm_display_starter_max"><?php echo esc_html($starter_max); ?></span></small>
            </div>

            <!-- Professional Plan -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h5 style="margin: 0 0 12px 0; color: #0f172a; font-size: 15px;">Professional Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / yr):</label>
                <input type="number" step="0.01" name="<?php echo esc_attr($slug); ?>_price_professional" value="<?php echo esc_attr($price_pro); ?>" class="kvm-input" required style="margin-bottom: 12px;">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label class="kvm-form-label">Min Slots:</label>
                        <input type="number" id="kvm_pro_min_display" value="<?php echo intval($starter_max) + 1; ?>" class="kvm-input" disabled style="background:#e2e8f0; cursor:not-allowed;">
                    </div>
                    <div>
                        <label class="kvm-form-label">Max Slots:</label>
                        <input type="number" id="kvm_pro_max_input" name="<?php echo esc_attr($slug); ?>_pro_max" value="<?php echo esc_attr($pro_max); ?>" class="kvm-input" required>
                    </div>
                </div>
                <small class="kvm-helper-text" style="display:block; margin-top:4px;">Range: <span id="kvm_display_pro_min"><?php echo intval($starter_max) + 1; ?></span> to <span id="kvm_display_pro_max"><?php echo esc_html($pro_max); ?></span></small>
            </div>

            <!-- Enterprise Plan -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h5 style="margin: 0 0 12px 0; color: #0f172a; font-size: 15px;">Enterprise Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / Lifetime):</label>
                <input type="number" step="0.01" name="<?php echo esc_attr($slug); ?>_price_enterprise" value="<?php echo esc_attr($price_ent); ?>" class="kvm-input" required style="margin-bottom: 12px;">
                
                <label class="kvm-form-label">Minimum Slots:</label>
                <input type="number" id="kvm_ent_min_display" value="<?php echo intval($pro_max) + 1; ?>" class="kvm-input" disabled style="background:#e2e8f0; cursor:not-allowed;">
                <small class="kvm-helper-text" style="display:block; margin-top:4px;">Starts at <span id="kvm_display_ent_min"><?php echo intval($pro_max) + 1; ?></span>+ Websites</small>
            </div>

        </div>

        <h4 class="kvm-group-heading spaced">⚙️ Global System & Grace Period Configuration</h4>
        <div class="kvm-form-group">
            <label class="kvm-form-label">Expiration Grace Period (Days):</label>
            <input type="number" name="<?php echo esc_attr($slug); ?>_grace_period_days" value="<?php echo esc_attr($grace_days); ?>" min="0" max="90" class="kvm-input compact" required>
            <small class="kvm-helper-text">Number of days client sites remain operational with warning notices after license key expiration.</small>
        </div>

        <div class="kvm-form-group">
            <label class="kvm-form-label">Client ZIP File Distribution Path:</label>
            <input type="text" name="<?php echo esc_attr($slug); ?>_zip_file_path" value="<?php echo esc_attr($zip_path); ?>" class="kvm-input" placeholder="/path/to/keyvault-client-v1.0.2-auto.zip">
        </div>

        <div class="kvm-form-group">
            <label class="kvm-form-label">Theme & Portal Accent Color:</label>
            <input type="color" name="<?php echo esc_attr($slug); ?>_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="kvm-color-picker">
        </div>

        <div class="kvm-form-group bottom-spaced">
            <label class="kvm-form-label">Customer Portal Login URL:</label>
            <input type="url" name="<?php echo esc_attr($slug); ?>_custom_login_url" value="<?php echo esc_attr($login_url); ?>" class="kvm-input">
        </div>

        <div class="kvm-form-group" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <label style="display: flex; align-items: center; cursor: pointer; gap: 12px;">
                <input type="checkbox" name="<?php echo esc_attr($slug); ?>_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall, 1); ?> style="width: 18px; height: 18px; accent-color: #2563eb;">
                <div>
                    <strong style="display: block; color: #0f172a; font-size: 14px;">Wipe Data on Uninstall</strong>
                    <span style="color: #64748b; font-size: 13px;">Automatically delete custom database tables, licenses, and plugin settings when the plugin is deleted.</span>
                </div>
            </label>
        </div>

        <button type="submit" name="<?php echo esc_attr($slug); ?>_save_all_settings" class="kvm-btn-submit">Save Global Settings</button>
    </form>
</div>