<?php
if (!defined('ABSPATH')) { exit; }
$slug = KVM_SLUG;
$public = KeyVault_Manager_Public::get_instance();
$token = isset($_GET[$slug . '_token']) ? sanitize_text_field(wp_unslash($_GET[$slug . '_token'])) : '';
$has_err = isset($_GET[$slug . '_err']);
?>
<div class="kvm-wrap kvm-wrap--narrow">
    <div class="kvm-card kvm-auth-card">
        <div class="kvm-auth-head">
            <h2>Create New Password</h2>
            <p>Please enter your new password below (minimum 8 characters).</p>
        </div>

        <?php if ($has_err): ?>
            <div class="kvm-alert kvm-alert--error">Invalid or expired reset token, or passwords did not match. Please try again.</div>
        <?php endif; ?>

        <form method="post" class="kvm-form kvm-form--stack">
            <?php wp_nonce_field($slug . '_reset_pass_action', $slug . '_reset_pass_nonce'); ?>
            <input type="hidden" name="<?php echo esc_attr($slug . '_reset_token'); ?>" value="<?php echo esc_attr($token); ?>">

            <div class="kvm-field">
                <label class="kvm-label" for="<?php echo esc_attr($slug . '_new_password'); ?>">New Password</label>
                <input type="password" id="<?php echo esc_attr($slug . '_new_password'); ?>" name="<?php echo esc_attr($slug . '_new_password'); ?>" required minlength="8" class="kvm-input" placeholder="Enter new password" autocomplete="new-password">
            </div>

            <div class="kvm-field">
                <label class="kvm-label" for="<?php echo esc_attr($slug . '_confirm_password'); ?>">Confirm New Password</label>
                <input type="password" id="<?php echo esc_attr($slug . '_confirm_password'); ?>" name="<?php echo esc_attr($slug . '_confirm_password'); ?>" required minlength="8" class="kvm-input" placeholder="Confirm new password" autocomplete="new-password">
            </div>

            <button type="submit" name="<?php echo esc_attr($slug . '_submit_reset_pass'); ?>" class="kvm-btn kvm-btn--primary kvm-btn--block">Update Password</button>

            <div class="kvm-auth-back">
                <a href="<?php echo esc_url($public->get_url()); ?>">&larr; Back to Login</a>
            </div>
        </form>
    </div>
</div>
