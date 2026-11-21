<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$sent = isset($_GET[$slug . '_sent']) && $_GET[$slug . '_sent'] === '1';
?>
<div class="kvm-wrap" style="max-width: 480px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="kvm-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;">Reset Password</h2>
            <p style="color: #64748b; font-size: 14px; margin: 0;">Enter your account email to receive a password reset link.</p>
        </div>

        <?php if ($sent): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                If an account exists with this email, we have sent a password reset link. Please check your inbox.
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field($slug . '_forgot_pass_action', $slug . '_forgot_pass_nonce'); ?>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px;">Email Address</label>
                <input type="email" name="<?php echo esc_attr($slug . '_forgot_email'); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;" placeholder="name@example.com">
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_forgot_pass'); ?>" style="width: 100%; background: #2563eb; color: #ffffff; border: none; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; margin-bottom: 15px;">Send Reset Link</button>

            <div style="text-align: center;">
                <a href="<?php echo esc_url($public->get_url()); ?>" style="color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600;">&larr; Back to Login</a>
            </div>
        </form>
    </div>
</div>