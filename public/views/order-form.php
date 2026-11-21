<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$price_starter = floatval(get_option('kvm_price_starter', '49'));

// Prefill from the signed-in portal user when available.
$current_user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
$prefill_name = $current_user ? $current_user->name : '';
$prefill_email = $current_user ? $current_user->email : '';
?>
<div class="kvm-wrap kvm-wrap--wide">
    <div class="kvm-card kvm-order-card">
        <div class="kvm-order-head">
            <span class="kvm-pill kvm-pill--accent">🔑 <?php echo esc_html(ucwords($slug)); ?> Licensing</span>
            <h2>Get Instant Access</h2>
            <p>Choose your website quota and key architecture mode</p>
        </div>

        <form method="post" class="kvm-form">
            <?php wp_nonce_field($slug . '_order_form_action', $slug . '_order_form_nonce'); ?>

            <div class="kvm-grid kvm-grid--2">
                <div class="kvm-field">
                    <label class="kvm-label">Full Name</label>
                    <input type="text" name="<?php echo esc_attr($slug); ?>_customer_name" value="<?php echo esc_attr($prefill_name); ?>" placeholder="Razu Ahammad" class="kvm-input" required>
                </div>
                <div class="kvm-field">
                    <label class="kvm-label">Email Address</label>
                    <input type="email" name="<?php echo esc_attr($slug); ?>_customer_email" value="<?php echo esc_attr($prefill_email); ?>" placeholder="razu@example.com" class="kvm-input" required>
                </div>
            </div>

            <div class="kvm-field">
                <label class="kvm-label">Number of Website Slots</label>
                <input type="number" id="kvm_quantity_input" name="<?php echo esc_attr($slug); ?>_quantity" value="1" min="1" max="500" class="kvm-input" required>
            </div>

            <div class="kvm-field">
                <label class="kvm-label">License Key Architecture Mode</label>
                <label class="kvm-option">
                    <input type="radio" name="<?php echo esc_attr($slug); ?>_license_mode" value="multi" checked>
                    <span>
                        <strong>One Key for All Websites</strong>
                        <small>Single license key quota shared dynamically across all activated sites.</small>
                    </span>
                </label>
                <label class="kvm-option">
                    <input type="radio" name="<?php echo esc_attr($slug); ?>_license_mode" value="individual">
                    <span>
                        <strong>Separate Individual Key Per Website</strong>
                        <small>Generates dedicated independent keys per site (ideal for client handoffs).</small>
                    </span>
                </label>
            </div>

            <div class="kvm-pricing-box">
                <div>
                    <span id="kvm-plan-badge" class="kvm-badge kvm-badge--starter">STARTER PLAN</span>
                    <h3 id="kvm-plan-title">Starter Tier (1 Website Slot)</h3>
                    <p class="kvm-muted">Rate: $<span id="kvm-rate-val"><?php echo esc_html($price_starter); ?></span> per website slot / yr</p>
                </div>
                <div class="kvm-pricing-total">
                    <span class="kvm-stat-label">Estimated Total</span>
                    <div class="kvm-total"><span id="kvm-price-val">$<?php echo esc_html($price_starter); ?></span><small id="kvm-billing-period"> / yr</small></div>
                </div>
            </div>

            <div class="kvm-field">
                <label class="kvm-label">Select Payment Gateway</label>
                <div class="kvm-grid kvm-grid--2">
                    <label class="kvm-option kvm-option--row">
                        <input type="radio" name="payment_method" value="stripe" checked>
                        <span>
                            <strong>Stripe Checkout</strong>
                            <small>Credit / Debit Card</small>
                        </span>
                    </label>
                    <label class="kvm-option kvm-option--row">
                        <input type="radio" name="payment_method" value="paypal">
                        <span>
                            <strong>PayPal</strong>
                            <small>PayPal Account or Card</small>
                        </span>
                    </label>
                </div>
            </div>

            <button type="submit" name="<?php echo esc_attr($slug); ?>_submit_order_form" class="kvm-btn kvm-btn--primary kvm-btn--block kvm-btn--lg">Generate &amp; Complete License Order &rarr;</button>
        </form>
    </div>
</div>
