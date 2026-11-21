<?php 
if (!defined('ABSPATH')) { exit; } 
$slug = KVM_SLUG;
$price_starter = floatval(get_option($slug . '_price_starter', '49'));

// Automatically fetch current user data if logged in to pre-fill form
$current_user = KeyVault_Manager_User_Auth::get_instance()->get_current_user();
$prefill_name = $current_user ? $current_user->name : '';
$prefill_email = $current_user ? $current_user->email : '';
?>
<div class="kvm-wrap" style="max-width:850px; margin:40px auto;">
    <div class="kvm-card" style="padding:45px;">
        <div style="text-align:center; margin-bottom:35px;">
            <span style="background:#eff6ff; color:#2563eb; padding:6px 16px; border-radius:20px; font-weight:700; font-size:13px; text-transform:uppercase;">🔑 <?php echo esc_html(ucwords($slug)); ?> Licensing</span>
            <h2 style="margin:15px 0 8px 0; color:#0f172a; font-size:32px; font-weight:800;">Get Instant Access</h2>
            <p style="color:#64748b; margin:0; font-size:15px;">Choose your website quota and key architecture mode</p>
        </div>

        <form method="post">
            <?php wp_nonce_field($slug . '_order_form_action', $slug . '_order_form_nonce'); ?>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:22px;">
                <div>
                    <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">Full Name</label>
                    <input type="text" name="<?php echo esc_attr($slug); ?>_customer_name" value="<?php echo esc_attr($prefill_name); ?>" placeholder="Razu Ahammad" class="kvm-input" required>
                </div>
                <div>
                    <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">Email Address</label>
                    <input type="email" name="<?php echo esc_attr($slug); ?>_customer_email" value="<?php echo esc_attr($prefill_email); ?>" placeholder="razu@example.com" class="kvm-input" required>
                </div>
            </div>

            <div style="margin-bottom:22px;">
                <label style="font-weight:700; display:block; margin-bottom:8px; color:#334155; font-size:14px;">Number of Website Slots</label>
                <input type="number" id="kvm_quantity_input" name="<?php echo esc_attr($slug); ?>_quantity" value="1" min="1" max="500" class="kvm-input" required>
            </div>

            <div style="margin-bottom:30px;">
                <label style="font-weight:700; display:block; margin-bottom:12px; color:#334155; font-size:14px;">License Key Architecture Mode</label>
                <label style="display:flex; align-items:flex-start; gap:12px; padding:16px 20px; border:1.5px solid #e2e8f0; border-radius:14px; margin-bottom:12px; cursor:pointer; background:#f8fafc;">
                    <input type="radio" name="<?php echo esc_attr($slug); ?>_license_mode" value="multi" checked style="margin-top:4px; transform:scale(1.2);"> 
                    <div>
                        <strong style="color:#0f172a; font-size:15px; display:block;">One Key for All Websites</strong>
                        <span style="color:#64748b; font-size:13px;">Single license key quota shared dynamically across all activated sites.</span>
                    </div>
                </label>
                <label style="display:flex; align-items:flex-start; gap:12px; padding:16px 20px; border:1.5px solid #e2e8f0; border-radius:14px; cursor:pointer; background:#f8fafc;">
                    <input type="radio" name="<?php echo esc_attr($slug); ?>_license_mode" value="individual" style="margin-top:4px; transform:scale(1.2);"> 
                    <div>
                        <strong style="color:#0f172a; font-size:15px; display:block;">Separate Individual Key Per Website</strong>
                        <span style="color:#64748b; font-size:13px;">Generates dedicated independent keys per site (ideal for client handoffs).</span>
                    </div>
                </label>
            </div>

            <div style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:18px; padding:28px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
                <div>
                    <span id="kvm-plan-badge" style="background:#2563eb; color:#ffffff; padding:5px 14px; border-radius:6px; font-weight:800; font-size:12px; text-transform:uppercase; display:inline-block; margin-bottom:8px;">STARTER PLAN</span>
                    <h3 id="kvm-plan-title" style="margin:0; font-size:20px; font-weight:800; color:#0f172a;">Starter Tier (1 Website Slot)</h3>
                    <p id="kvm-per-key-rate" style="margin:4px 0 0 0; color:#64748b; font-size:14px; font-weight:600;">Rate: $<span id="kvm-rate-val"><?php echo esc_html($price_starter); ?></span> per website slot / yr</p>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px; color:#64748b; font-weight:800; text-transform:uppercase;">ESTIMATED TOTAL</div>
                    <div style="font-size:32px; font-weight:900; color:#2563eb; line-height:1.2;"><span id="kvm-price-val">$<?php echo esc_html($price_starter); ?></span><small id="kvm-billing-period" style="font-size:14px; color:#64748b; font-weight:600;"> / yr</small></div>
                </div>
            </div>
            
            <div style="margin-bottom:25px;">
                <label style="font-weight:700; display:block; margin-bottom:12px; color:#334155; font-size:14px;">Select Payment Gateway</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <label style="display:flex; align-items:center; gap:10px; padding:14px 18px; border:1.5px solid #e2e8f0; border-radius:12px; cursor:pointer; background:#f8fafc;">
                        <input type="radio" name="payment_method" value="stripe" checked style="transform:scale(1.2);">
                        <div>
                            <strong style="color:#0f172a; font-size:14px; display:block;">Stripe Checkout</strong>
                            <span style="color:#64748b; font-size:12px;">Credit / Debit Card</span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:10px; padding:14px 18px; border:1.5px solid #e2e8f0; border-radius:12px; cursor:pointer; background:#f8fafc;">
                        <input type="radio" name="payment_method" value="paypal" style="transform:scale(1.2);">
                        <div>
                            <strong style="color:#0f172a; font-size:14px; display:block;">PayPal</strong>
                            <span style="color:#64748b; font-size:12px;">PayPal Account or Card</span>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" name="<?php echo esc_attr($slug); ?>_submit_order_form" class="kvm-btn">Generate & Complete License Order &rarr;</button>
        </form>
    </div>
</div>