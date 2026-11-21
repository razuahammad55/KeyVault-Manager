<?php
if (!defined('ABSPATH')) { exit; }

// Canonical kvm_ options.
$zip_path            = get_option('kvm_zip_file_path', '');
$primary_color       = get_option('kvm_primary_color', '#2563eb');
$grace_days          = get_option('kvm_grace_period_days', '7');
$login_url           = get_option('kvm_custom_login_url', '');
$price_starter       = get_option('kvm_price_starter', '49');
$starter_max         = get_option('kvm_starter_max', '9');
$price_pro           = get_option('kvm_price_professional', '39');
$pro_max             = get_option('kvm_pro_max', '99');
$price_ent           = get_option('kvm_price_enterprise', '30');
$delete_on_uninstall = get_option('kvm_delete_data_on_uninstall', 0);
$client_slug         = get_option('kvm_client_plugin_slug', 'client-plugin');
$client_version      = get_option('kvm_client_version', '1.0.0');
$wipe_error          = isset($_GET['wipe_error']);

if (isset($_GET['saved'])) echo '<div class="kvm-notice success">✅ Settings saved successfully!</div>';
if ($wipe_error) {
    echo '<div class="kvm-notice danger">❌ <strong>Settings were not saved.</strong> Enabling “Wipe Data on Uninstall” requires the same installation passkey used to unlock the hub. Please try again.</div>';
}

$slug = KVM_SLUG;
$shortcode_cards = array(
    array('code' => '[' . $slug . '_client_dashboard]', 'title' => 'Account & Client Dashboard', 'desc' => 'Renders the secure client portal: licenses, downloads, profile and security.'),
    array('code' => '[' . $slug . '_order_form]', 'title' => 'Order / Checkout Form', 'desc' => 'Displays the frontend order form for purchasing license keys.'),
    array('code' => '[' . $slug . '_login_form]', 'title' => 'Portal Login Form', 'desc' => 'Standalone sign-in form for the customer portal.'),
    array('code' => '[' . $slug . '_customer_portal]', 'title' => 'Customer Portal (alias)', 'desc' => 'Alias shortcode — identical to the client dashboard.'),
);

$updater_snippet = KeyVault_Manager_Updater::get_client_snippet();
?>

<div class="kvm-card-panel">
    <h3 class="kvm-section-heading">⚡ Shortcodes Reference</h3>
    <p class="kvm-section-sub">Copy and paste these shortcodes onto any WordPress page to render front-end forms or the customer dashboard.</p>

    <div class="kvm-shortcodes-grid">
        <?php foreach ($shortcode_cards as $card) : ?>
            <div class="kvm-shortcode-card">
                <strong class="kvm-shortcode-title"><?php echo esc_html($card['title']); ?></strong>
                <code class="kvm-shortcode-code" tabindex="0"><?php echo esc_html($card['code']); ?></code>
                <p class="kvm-shortcode-desc"><?php echo esc_html($card['desc']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="kvm-card-panel">
    <h3>KeyVault Engine &amp; Dynamic Pricing Tiers</h3>
    <form method="post" id="kvm-settings-form">
        <?php wp_nonce_field('kvm_all_settings_action', 'kvm_all_settings_nonce'); ?>

        <h4 class="kvm-group-heading">💰 Plan Pricing &amp; Slot Range Configuration</h4>
        <div class="kvm-pricing-grid">
            <div class="kvm-pricing-card">
                <h5>Starter Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / yr):</label>
                <input type="number" step="0.01" name="kvm_price_starter" value="<?php echo esc_attr($price_starter); ?>" class="kvm-input" required>
                <label class="kvm-form-label">Max Website Slots:</label>
                <input type="number" id="kvm_starter_max_input" name="kvm_starter_max" value="<?php echo esc_attr($starter_max); ?>" class="kvm-input" min="1" required>
                <small class="kvm-helper-text">Range: 1 to <span id="kvm_display_starter_max"><?php echo esc_html($starter_max); ?></span></small>
            </div>

            <div class="kvm-pricing-card">
                <h5>Professional Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / yr):</label>
                <input type="number" step="0.01" name="kvm_price_professional" value="<?php echo esc_attr($price_pro); ?>" class="kvm-input" required>
                <div class="kvm-grid--inline">
                    <div>
                        <label class="kvm-form-label">Min Slots:</label>
                        <input type="number" id="kvm_pro_min_display" value="<?php echo esc_attr(intval($starter_max) + 1); ?>" class="kvm-input" disabled>
                    </div>
                    <div>
                        <label class="kvm-form-label">Max Slots:</label>
                        <input type="number" id="kvm_pro_max_input" name="kvm_pro_max" value="<?php echo esc_attr($pro_max); ?>" class="kvm-input" required>
                    </div>
                </div>
                <small class="kvm-helper-text">Range: <span id="kvm_display_pro_min"><?php echo esc_html(intval($starter_max) + 1); ?></span> to <span id="kvm_display_pro_max"><?php echo esc_html($pro_max); ?></span></small>
            </div>

            <div class="kvm-pricing-card">
                <h5>Enterprise Plan</h5>
                <label class="kvm-form-label">Price per Key ($ / Lifetime):</label>
                <input type="number" step="0.01" name="kvm_price_enterprise" value="<?php echo esc_attr($price_ent); ?>" class="kvm-input" required>
                <label class="kvm-form-label">Minimum Slots:</label>
                <input type="number" id="kvm_ent_min_display" value="<?php echo esc_attr(intval($pro_max) + 1); ?>" class="kvm-input" disabled>
                <small class="kvm-helper-text">Starts at <span id="kvm_display_ent_min"><?php echo esc_html(intval($pro_max) + 1); ?></span>+ Websites</small>
            </div>
        </div>

        <h4 class="kvm-group-heading">⚙️ Global System &amp; Grace Period Configuration</h4>
        <div class="kvm-settings-grid">
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_grace_period_days">Expiration Grace Period (Days)</label>
                <input type="number" id="kvm_grace_period_days" name="kvm_grace_period_days" value="<?php echo esc_attr($grace_days); ?>" min="0" max="365" class="kvm-input compact" required>
                <small class="kvm-helper-text">Days client sites remain operational with warnings after license expiration.</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_zip_file_path">Client ZIP File Distribution Path</label>
                <input type="text" id="kvm_zip_file_path" name="kvm_zip_file_path" value="<?php echo esc_attr($zip_path); ?>" class="kvm-input" placeholder="/absolute/path/to/client-plugin-v1.0.2.zip  —  or a full https:// URL">
                <small class="kvm-helper-text">Absolute server path or remote URL of the ZIP served by the key-gated download route.</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_client_plugin_slug">Client Product Slug</label>
                <input type="text" id="kvm_client_plugin_slug" name="kvm_client_plugin_slug" value="<?php echo esc_attr($client_slug); ?>" class="kvm-input" placeholder="client-plugin">
                <small class="kvm-helper-text">Directory/slug of the distributed product (used by the update server).</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_client_version">Client Product Version</label>
                <input type="text" id="kvm_client_version" name="kvm_client_version" value="<?php echo esc_attr($client_version); ?>" class="kvm-input" placeholder="1.0.2">
                <small class="kvm-helper-text">Version advertised by the update-check endpoint. Raise it when you upload a new ZIP.</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_primary_color">Theme &amp; Portal Accent Color</label>
                <input type="color" id="kvm_primary_color" name="kvm_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="kvm-color-picker">
                <small class="kvm-helper-text">Used in admin UI accents, portal, and email templates.</small>
            </div>
            <div class="kvm-form-group">
                <label class="kvm-form-label" for="kvm_custom_login_url">Customer Portal Login URL</label>
                <input type="url" id="kvm_custom_login_url" name="kvm_custom_login_url" value="<?php echo esc_attr($login_url); ?>" class="kvm-input" placeholder="https://yoursite.com/account">
                <small class="kvm-helper-text">Leave blank to use the auto-created <code>/<?php echo esc_html(KVM_SLUG); ?>-account</code> page.</small>
            </div>
        </div>

        <h4 class="kvm-group-heading">📥 Client Product Updater Snippet</h4>
        <p class="kvm-section-sub">Paste this into the plugin you distribute so customer products receive updates from this hub using their license key (requires the ZIP path + version above).</p>
        <textarea id="kvm-updater-snippet" class="kvm-textarea kvm-snippet" rows="10" readonly><?php echo esc_textarea($updater_snippet); ?></textarea>
        <div class="kvm-form-actions left">
            <button type="button" class="kvm-btn compact" id="kvm-copy-snippet" data-kvm-copy-target="kvm-updater-snippet">Copy Updater Snippet</button>
        </div>

        <h4 class="kvm-group-heading">🔒 Security &amp; Uninstall</h4>
        <div class="kvm-wipe-block">
            <label class="kvm-wipe-row">
                <input type="checkbox" id="kvm-wipe-toggle" name="kvm_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall, 1); ?>>
                <span>
                    <strong>Wipe Data on Uninstall</strong>
                    <small>Deletes <em>everything</em> when the plugin is uninstalled: all five custom tables (licenses, activations, users, orders, logs), every plugin option (including gateway credentials), all transients and rate-limit counters, and the auto-created portal page. Saving this option requires the installation passkey.</small>
                </span>
            </label>

            <div class="kvm-wipe-passkey" id="kvm-wipe-passkey-row" <?php echo $delete_on_uninstall ? '' : 'style="display:none;"'; ?>>
                <label class="kvm-form-label" for="kvm_wipe_passkey">Confirm with Installation Passkey</label>
                <input type="password" id="kvm_wipe_passkey" name="kvm_wipe_passkey" class="kvm-input compact" placeholder="Same passkey used to unlock the hub" autocomplete="current-password">
                <small class="kvm-helper-text">Required because this setting controls irreversible data deletion.</small>
            </div>
        </div>

        <div class="kvm-form-actions">
            <button type="submit" name="kvm_save_all_settings" class="kvm-btn kvm-btn--primary">Save Global Settings</button>
        </div>
    </form>
</div>

<div class="kvm-card-panel kvm-danger-panel">
    <h3>Danger Zone</h3>
    <div class="kvm-danger-row">
        <div>
            <strong>Lock the Hub</strong>
            <p class="kvm-muted">Re-enable the installation passkey gate. The next visit to the hub will require the master passkey to unlock again.</p>
        </div>
        <form method="post" onsubmit="return confirm('Lock the hub now? You will need the installation passkey to unlock it again.');">
            <?php wp_nonce_field('kvm_lock_hub_action', 'kvm_lock_hub_nonce'); ?>
            <button type="submit" name="kvm_lock_hub" class="kvm-btn kvm-btn--danger">🔒 Lock Hub</button>
        </form>
    </div>
</div>
