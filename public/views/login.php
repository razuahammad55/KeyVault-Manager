<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$err_param = $slug . '_err';
$msg_param = $slug . '_msg';
?>
<div class="kvm-wrap" style="max-width: 480px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="kvm-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;">Client Sign In</h2>
            <p style="color: #64748b; font-size: 14px; margin: 0;">Access your licenses, downloads, and account settings.</p>
        </div>

        <?php if (isset($_GET[$err_param]) && $_GET[$err_param] === 'invalid_login'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                Invalid email address or password. Please try again.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET[$msg_param]) && $_GET[$msg_param] === 'pass_reset_success'): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; text-align: center;">
                Password reset successfully! You can now log in with your new password.
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field($slug . '_login_action', $slug . '_login_nonce'); ?>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px;">Email Address</label>
                <input type="email" name="<?php echo esc_attr($slug . '_login_email'); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;" placeholder="name@example.com">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px;">Password</label>
                <input type="password" name="<?php echo esc_attr($slug . '_login_pass'); ?>" required style="width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; outline: none; box-sizing: border-box;" placeholder="••••••••">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-bottom: 25px;">
                <a href="<?php echo esc_url($public->get_url(array('auth' => 'forgot_pass'))); ?>" style="color: #2563eb; text-decoration: none; font-size: 13px; font-weight: 600;">Forgot password?</a>
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_login'); ?>" style="width: 100%; background: #2563eb; color: #ffffff; border: none; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer;">Sign In to Portal</button>
        </form>
    </div>
</div>