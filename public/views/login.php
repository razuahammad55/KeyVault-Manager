<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$err_param = $slug . '_err';
$msg_param = $slug . '_msg';
?>
<div class="kvm-wrap kvm-wrap--narrow">
    <div class="kvm-card kvm-auth-card">
        <div class="kvm-auth-head">
            <h2>Client Sign In</h2>
            <p>Access your licenses, downloads, and account settings.</p>
        </div>

        <?php if (isset($_GET[$err_param]) && $_GET[$err_param] === 'invalid_login'): ?>
            <div class="kvm-alert kvm-alert--error">Invalid email address or password. Please try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET[$err_param]) && $_GET[$err_param] === 'too_many_attempts'): ?>
            <div class="kvm-alert kvm-alert--error">Too many login attempts. Please wait a few minutes and try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET[$msg_param]) && $_GET[$msg_param] === 'pass_reset_success'): ?>
            <div class="kvm-alert kvm-alert--success">Password reset successfully! You can now log in with your new password.</div>
        <?php endif; ?>

        <?php if (isset($_GET[$msg_param]) && $_GET[$msg_param] === 'logged_out'): ?>
            <div class="kvm-alert kvm-alert--success">You have been signed out.</div>
        <?php endif; ?>

        <form method="post" class="kvm-form kvm-form--stack">
            <?php wp_nonce_field($slug . '_login_action', $slug . '_login_nonce'); ?>

            <div class="kvm-field">
                <label class="kvm-label" for="<?php echo esc_attr($slug . '_login_email'); ?>">Email Address</label>
                <input type="email" id="<?php echo esc_attr($slug . '_login_email'); ?>" name="<?php echo esc_attr($slug . '_login_email'); ?>" required class="kvm-input" placeholder="name@example.com" autocomplete="username">
            </div>

            <div class="kvm-field">
                <label class="kvm-label" for="<?php echo esc_attr($slug . '_login_pass'); ?>">Password</label>
                <input type="password" id="<?php echo esc_attr($slug . '_login_pass'); ?>" name="<?php echo esc_attr($slug . '_login_pass'); ?>" required class="kvm-input" placeholder="••••••••" autocomplete="current-password">
            </div>

            <div class="kvm-auth-forgot">
                <a href="<?php echo esc_url($public->get_url(array('auth' => 'forgot_pass'))); ?>">Forgot password?</a>
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_login'); ?>" class="kvm-btn kvm-btn--primary kvm-btn--block">Sign In to Portal</button>
        </form>
    </div>
</div>
