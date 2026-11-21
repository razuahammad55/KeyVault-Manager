<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Administrative access gate.
 *
 * The hub stays locked behind a master passkey until it is unlocked once.
 * The passkey hash defaults to the KVM_ACCESS_HASH build constant and can be
 * overridden per install via the `kvm_access_hash` filter (used by resellers).
 */
class KeyVault_Manager_Gate {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'handle_passkey_submission'), 5);
        add_action('admin_init', array($this, 'enforce_passkey_gate'), 10);
    }

    public function access_hash() {
        $hash = defined('KVM_ACCESS_HASH') ? KVM_ACCESS_HASH : '';
        return (string) apply_filters('kvm_access_hash', $hash);
    }

    public function is_verified() {
        return (bool) get_option('kvm_plugin_unlocked', false);
    }

    /**
     * Verify a passkey against the configured hash. Fails closed when no
     * hash is configured.
     */
    public function verify_passkey($input) {
        $hash = $this->access_hash();
        if (empty($hash)) {
            return false;
        }
        return wp_check_password((string) $input, $hash);
    }

    private function is_current_page() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        return ($page === 'keyvault-manager');
    }

    public function enforce_passkey_gate() {
        if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (!$this->is_current_page() || $this->is_verified()) {
            return;
        }
        $this->render_gate_screen();
        exit;
    }

    public function handle_passkey_submission() {
        if (!isset($_POST['kvm_passkey_submit']) || !$this->is_current_page()) {
            return;
        }

        if (!isset($_POST['kvm_passkey_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kvm_passkey_nonce'])), 'kvm_passkey_action')) {
            wp_die(esc_html__('Security check failed.', 'cdf'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized action.', 'cdf'));
        }

        // Raw password — never run through sanitize_text_field.
        $input_passkey = isset($_POST['kvm_passkey']) ? wp_unslash($_POST['kvm_passkey']) : '';

        if ($this->verify_passkey($input_passkey)) {
            update_option('kvm_plugin_unlocked', true);
            KeyVault_Manager_Logger::log('gate', '', '', 200, 'Hub unlocked by administrator.');
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
        } else {
            KeyVault_Manager_Logger::log('gate', '', '', 403, 'Failed passkey attempt.');
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&passkey_error=1'));
        }
        exit;
    }

    public function render_gate_screen() {
        if ($this->is_verified()) {
            return;
        }

        $has_error = isset($_GET['passkey_error']);
        $brand = kvm_brand_name();
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($brand . ' — Access Gate'); ?></title>
    <?php wp_enqueue_style('kvm-admin-style', KVM_URL . 'admin/assets/css/admin-style.css', array(), KVM_VERSION); ?>
    <?php wp_print_styles('kvm-admin-style'); ?>
</head>
<body class="kvm-gate-body">
    <div class="kvm-passkey-wrap">
        <div class="kvm-passkey-card">
            <div class="kvm-passkey-icon" aria-hidden="true">🔐</div>
            <h2><?php echo esc_html($brand . ' Activation Gate'); ?></h2>
            <p><?php echo esc_html__('Enter the master installation passkey to unlock and activate this plugin on your domain. Once verified, this prompt will not appear again.', 'cdf'); ?></p>

            <?php if ($has_error) : ?>
                <div class="kvm-notice danger">❌ <?php echo esc_html__('Incorrect passkey. Please try again.', 'cdf'); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('kvm_passkey_action', 'kvm_passkey_nonce'); ?>
                <div class="kvm-form-group">
                    <input type="password" name="kvm_passkey" class="kvm-input large" placeholder="<?php esc_attr_e('Enter Installation Passkey…', 'cdf'); ?>" required autofocus autocomplete="current-password">
                </div>
                <button type="submit" name="kvm_passkey_submit" class="kvm-btn primary full-width"><?php echo esc_html__('Unlock & Activate Plugin', 'cdf'); ?></button>
            </form>
        </div>
    </div>
</body>
</html>
        <?php
    }
}
