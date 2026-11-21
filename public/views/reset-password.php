<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$token = sanitize_text_field($_GET[$slug . '_token'] ?? '');
$has_err = isset($_GET[$slug . '_err']);
?>
<div class="kvm-wrap" style="max-width: 480px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="kvm-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;">Create New Password</h2>
            <p style="color: #64748b; font-size: 14px; margin: 0;">Please enter your new password below.</p>
        </div>

        <?php if ($has_err): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                Invalid or expired reset token, or passwords did not match. Please try again.
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field($slug . '_reset_pass_action', $slug . '_reset_pass_nonce'); ?>
            <input type="hidden" name="<?php echo esc_attr($slug . '_reset_token'); ?>" value="<?php echo esc_attr($token); ?>">
            
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px;">New Password</label>
                <input type="password" name="<?php echo esc_attr($slug . '_new_password'); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;" placeholder="Enter new password">
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px;">Confirm New Password</label>
                <input type="password" name="<?php echo esc_attr($slug . '_confirm_password'); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;" placeholder="Confirm new password">
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_reset_pass'); ?>" style="width: 100%; background: #2563eb; color: #ffffff; border: none; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; margin-bottom: 15px;">Update Password</button>

            <div style="text-align: center;">
                <a href="<?php echo esc_url($public->get_url()); ?>" style="color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600;">&larr; Back to Login</a>
            </div>
        </form>
    </div>
</div>