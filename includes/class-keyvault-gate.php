<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Gate {
    
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!defined('KVM_ACCESS_HASH')) {
            define('KVM_ACCESS_HASH', '$wp$2y$12$D7SP1Ax4ZPMcd1bHtmxqU.lKNuVw5AVxFgE4y.1PezjUcZI2YL1B2');
        }
        add_action('admin_init', array($this, 'handle_passkey_submission'));
        add_action('admin_init', array($this, 'enforce_passkey_gate'));
    }
	
	public function is_verified() {
    return (bool) get_option('kvm_plugin_unlocked', false);
	}    
	
	public function enforce_passkey_gate() {
    if (!is_admin() || wp_doing_ajax()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    if ($page === 'keyvault-manager' && !$this->is_verified()) {
        $this->render_gate_screen();
        exit;
    }
}

    public function handle_passkey_submission() {
        if (!isset($_POST['kvm_passkey_submit'])) {
            return;
        }

        if (!isset($_POST['kvm_passkey_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kvm_passkey_nonce'])), 'kvm_passkey_action')) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized action.');
        }

        $input_passkey = isset($_POST['kvm_passkey']) ? sanitize_text_field(wp_unslash($_POST['kvm_passkey'])) : '';
        $stored_hash = KVM_ACCESS_HASH;

        if (wp_check_password($input_passkey, $stored_hash)) {
            update_option('kvm_plugin_unlocked', true);
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
            exit;
        } else {
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&passkey_error=1'));
            exit;
        }
    }

    public function render_gate_screen() {
        if ($this->is_verified()) {
            return;
        }

        $has_error = isset($_GET['passkey_error']);
        ?>
        <div class="wrap kvm-passkey-wrap">
            <div class="kvm-passkey-card">
                <div class="kvm-passkey-icon">🔐</div>
                <h2>KeyVault Manager Activation Gate</h2>
                <p>Enter the master installation passkey to unlock and activate this plugin on your domain. Once verified, this prompt will never appear again.</p>

                <?php if ($has_error) : ?>
                    <div class="kvm-notice danger">❌ Incorrect passkey. Please try again.</div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('kvm_passkey_action', 'kvm_passkey_nonce'); ?>
                    <div class="kvm-form-group">
                        <input type="password" name="kvm_passkey" class="kvm-input large" placeholder="Enter Installation Passkey..." required autofocus>
                    </div>
                    <button type="submit" name="kvm_passkey_submit" class="kvm-btn-submit full-width">Unlock & Activate Plugin</button>
                </form>
            </div>
        </div>
        <style>
            .kvm-passkey-wrap { display: flex; align-items: center; justify-content: center; min-height: 75vh; }
            .kvm-passkey-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 40px; width: 100%; max-width: 440px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05), 0 10px 10px -5px rgba(0,0,0,0.04); text-align: center; }
            .kvm-passkey-icon { font-size: 42px; margin-bottom: 15px; }
            .kvm-passkey-card h2 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; }
            .kvm-passkey-card p { font-size: 13px; color: #64748b; margin-bottom: 25px; line-height: 1.5; }
            .kvm-input.large { padding: 12px 16px; font-size: 15px; width: 100%; text-align: center; letter-spacing: 1px; }
            .kvm-notice.danger { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 12px; margin-bottom: 20px; text-align: left; font-weight: 700; }
            .kvm-btn-submit.full-width { width: 100%; background: #2563eb; color: #fff; border: none; padding: 12px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; }
            .kvm-btn-submit.full-width:hover { background: #1d4ed8; }
        </style>
        <?php
        exit;
    }
}

// Temporary test reset notice aligned with kvm_plugin_unlocked option
add_action('admin_notices', function() {
    if (isset($_GET['reset_kvm_passkey']) && check_admin_referer('kvm_reset_passkey_nonce')) {
        update_option('kvm_plugin_unlocked', false);
        echo '<div class="notice notice-success is-dismissible"><p>Passkey lock reset successfully.</p></div>';
    }
    $url = wp_nonce_url(add_query_arg('reset_kvm_passkey', '1'), 'kvm_reset_passkey_nonce');
    echo '<div class="notice notice-info"><p><a href="' . esc_url($url) . '" class="button button-primary">Reset Passkey Lock</a></p></div>';
});