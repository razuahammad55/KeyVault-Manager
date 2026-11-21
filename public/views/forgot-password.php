<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$sent = isset($_GET[$slug . '_sent']) && $_GET[$slug . '_sent'] === '1';
?>
<div class="kvm-wrap kvm-wrap--narrow">
    <div class="kvm-card kvm-auth-card">
        <div class="kvm-auth-head">
            <h2>Reset Password</h2>
            <p>Enter your account email to receive a password reset link.</p>
        </div>

        <?php if ($sent): ?>
            <div class="kvm-alert kvm-alert--success">If an account exists with this email, we have sent a password reset link. Please check your inbox.</div>
        <?php endif; ?>

        <form method="post" class="kvm-form kvm-form--stack">
            <?php wp_nonce_field($slug . '_forgot_pass_action', $slug . '_forgot_pass_nonce'); ?>

            <div class="kvm-field">
                <label class="kvm-label" for="<?php echo esc_attr($slug . '_forgot_email'); ?>">Email Address</label>
                <input type="email" id="<?php echo esc_attr($slug . '_forgot_email'); ?>" name="<?php echo esc_attr($slug . '_forgot_email'); ?>" required class="kvm-input" placeholder="name@example.com" autocomplete="username">
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_forgot_pass'); ?>" class="kvm-btn kvm-btn--primary kvm-btn--block">Send Reset Link</button>

            <div class="kvm-auth-back">
                <a href="<?php echo esc_url($public->get_url()); ?>">&larr; Back to Login</a>
            </div>
        </form>
    </div>
</div>
