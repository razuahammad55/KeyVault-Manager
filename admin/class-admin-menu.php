<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Admin {
    private static $instance = null;
    private static $page_hook = '';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_menu() {
        self::$page_hook = add_menu_page(
            kvm_brand_name(),
            'KeyVault Hub',
            'manage_options',
            'keyvault-manager',
            array($this, 'render_page'),
            'dashicons-lock',
            30
        );
    }

    public function enqueue_assets($hook) {
        if (self::$page_hook === '' || $hook !== self::$page_hook) {
            return;
        }
        wp_enqueue_style('kvm-admin-style', KVM_URL . 'admin/assets/css/admin-style.css', array(), KVM_VERSION);

        // Dynamic accent color (single CSS variable — everything else lives in the stylesheet).
        $accent = sanitize_hex_color(get_option('kvm_primary_color', '#2563eb'));
        if (!$accent) {
            $accent = '#2563eb';
        }
        wp_add_inline_style('kvm-admin-style', ':root{--kvm-accent:' . $accent . ';}');

        wp_enqueue_script('kvm-admin-script', KVM_URL . 'admin/assets/js/admin-script.js', array('jquery'), KVM_VERSION, true);
    }

    /* ------------------------------------------------------------------
     * One-time flash messages (e.g. generated keys, temp passwords).
     * ---------------------------------------------------------------- */

    private function flash($message, $keys = array()) {
        set_transient('kvm_flash_' . get_current_user_id(), array(
            'message' => $message,
            'keys'    => array_map('strval', (array) $keys),
        ), 120);
    }

    private function take_flash() {
        $flash = get_transient('kvm_flash_' . get_current_user_id());
        if ($flash) {
            delete_transient('kvm_flash_' . get_current_user_id());
        }
        return $flash;
    }

    private function redirect($args) {
        wp_safe_redirect(admin_url(add_query_arg($args, 'admin.php?page=keyvault-manager')));
        exit;
    }

    private function verify_nonce($action, $field) {
        if (!isset($_POST[$field]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field])), $action)) {
            wp_die(esc_html__('Security check failed. Please go back and try again.', 'cdf'), 403);
        }
    }

    /* ------------------------------------------------------------------
     * All POST/GET actions (capability-checked, nonce-verified).
     * ---------------------------------------------------------------- */

    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $db = KeyVault_Manager_DB::get_instance();

        /* --- Issue new license keys ------------------------------------ */
        if (isset($_POST['kvm_generate_key'])) {
            $this->verify_nonce('kvm_gen_action', 'kvm_gen_nonce');

            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            if ($user_id <= 0) {
                $this->redirect(array('tab' => 'licenses', 'issue_error' => '1'));
            }

            $qty  = max(1, intval(isset($_POST['custom_sites']) ? $_POST['custom_sites'] : 1));
            $mode = (isset($_POST['license_mode']) && $_POST['license_mode'] === 'individual') ? 'individual' : 'multi';
            $expiry_choice = isset($_POST['expiry_choice']) ? sanitize_key(wp_unslash($_POST['expiry_choice'])) : 'standard';
            $expiry_type   = in_array($expiry_choice, array('lifetime', '6_months', '3_years'), true) ? $expiry_choice : '1_year';
            $notes  = isset($_POST['kvm_notes']) ? sanitize_textarea_field(wp_unslash($_POST['kvm_notes'])) : '';
            $custom = isset($_POST['custom_expires_at']) ? sanitize_text_field(wp_unslash($_POST['custom_expires_at'])) : null;

            $keys = $db->generate_license_keys('', '', $qty, $mode, $expiry_type, $notes, $user_id, $custom, array('gateway' => 'Manual (Admin)'));
            $this->flash('License keys issued successfully. Copy them now — they are stored hashed in the database.', $keys);
            $this->redirect(array('tab' => 'licenses', 'created' => '1'));
        }

        /* --- Update expiration ----------------------------------------- */
        if (isset($_POST['kvm_update_expiry'])) {
            $this->verify_nonce('kvm_update_expiry_action', 'kvm_update_expiry_nonce');

            $license_id  = intval(isset($_POST['license_id']) ? $_POST['license_id'] : 0);
            $expiry_type = isset($_POST['expiry_type']) ? sanitize_key(wp_unslash($_POST['expiry_type'])) : 'extend_1y';
            $custom_date = isset($_POST['custom_expiry_date']) ? sanitize_text_field(wp_unslash($_POST['custom_expiry_date'])) : '';

            if ($expiry_type === 'custom' && empty($custom_date)) {
                $this->redirect(array('tab' => 'licenses', 'expiry_error' => '1'));
            }
            $db->update_expiration($license_id, $expiry_type, $custom_date);
            $this->redirect(array('tab' => 'licenses', 'expiry_updated' => '1'));
        }

        /* --- Users: create --------------------------------------------- */
        if (isset($_POST['kvm_create_user'])) {
            $this->verify_nonce('kvm_create_user_action', 'kvm_create_user_nonce');

            $name     = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : '';
            $email    = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
            $password = isset($_POST['user_password']) ? wp_unslash($_POST['user_password']) : '';

            $res = KeyVault_Manager_User_Auth::get_instance()->get_or_create_user($name, $email, $password);
            if ($res['is_new']) {
                $this->flash('User created. One-time temporary password (share it securely, it is not stored): ' . $res['temp_pass']);
            } else {
                $this->flash('A user with this email already exists. Passwords are not changed here — use the “Email New Password” action instead.');
            }
            $this->redirect(array('tab' => 'users', 'user_created' => '1'));
        }

        /* --- Users: update --------------------------------------------- */
        if (isset($_POST['kvm_update_user'])) {
            $this->verify_nonce('kvm_update_user_action', 'kvm_update_user_nonce');

            $user_id  = intval(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
            $name     = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : '';
            $email    = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';
            $password = isset($_POST['user_password']) ? wp_unslash($_POST['user_password']) : '';

            KeyVault_Manager_User_Auth::get_instance()->update_user_info($user_id, $name, $email, $password);
            $message = 'User information updated.';
            if ($password !== '') {
                $message .= ' New password (one-time display, share securely): ' . $password;
            }
            $this->flash($message);
            $this->redirect(array('tab' => 'users', 'user_updated' => '1'));
        }

        /* --- Users: email a fresh temporary password -------------------- */
        if (isset($_GET['kvm_user_action']) && $_GET['kvm_user_action'] === 'new_password' && isset($_GET['user'])) {
            $user_id = intval($_GET['user']);
            if (!check_admin_referer('kvm_new_password_' . $user_id)) {
                wp_die(esc_html__('Security check failed.', 'cdf'), 403);
            }

            global $wpdb;
            $table = KeyVault_Manager_DB::table('users');
            $user  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id));
            if ($user) {
                $temp = KeyVault_Manager_User_Auth::generate_temp_password(14);
                KeyVault_Manager_User_Auth::get_instance()->set_password($user_id, $temp);
                KeyVault_Manager_Emails::get_instance()->send_event_email('user_welcome_portal', $user->email, array(
                    'customer_name'  => $user->name,
                    'customer_email' => $user->email,
                    'temp_pass'      => $temp,
                ));
                $this->flash('New temporary password generated and emailed to ' . $user->email . '. One-time display: ' . $temp);
            }
            $this->redirect(array('tab' => 'users'));
        }

        /* --- Users: delete (keys only, or account + keys) --------------- */
        if (isset($_POST['kvm_delete_user_choice_action'])) {
            $this->verify_nonce('kvm_delete_user_choice', 'kvm_delete_user_choice_nonce');

            $user_id = intval(isset($_POST['user_id']) ? $_POST['user_id'] : 0);
            $choice  = isset($_POST['delete_type']) ? sanitize_key(wp_unslash($_POST['delete_type'])) : 'keys_only';

            if ($choice === 'keys_only') {
                $db->delete_user_keys_only($user_id);
            } else {
                $db->delete_user_and_all_licenses($user_id);
            }
            $this->redirect(array('tab' => 'users', 'deleted' => '1'));
        }

        /* --- Email settings ---------------------------------------------- */
        if (isset($_POST['kvm_save_email_settings'])) {
            $this->verify_nonce('kvm_email_settings_action', 'kvm_email_settings_nonce');

            if (isset($_POST['kvm_email_from_name'])) {
                KeyVault_Manager_DB::update_opt('email_from_name', sanitize_text_field(wp_unslash($_POST['kvm_email_from_name'])));
            }
            if (isset($_POST['kvm_email_from_email'])) {
                KeyVault_Manager_DB::update_opt('email_from_email', sanitize_email(wp_unslash($_POST['kvm_email_from_email'])));
            }
            if (isset($_POST['kvm_email_reply_to'])) {
                KeyVault_Manager_DB::update_opt('email_reply_to', sanitize_email(wp_unslash($_POST['kvm_email_reply_to'])));
            }
            if (isset($_POST['kvm_admin_email_recipients'])) {
                update_option('kvm_admin_email_recipients', sanitize_text_field(wp_unslash($_POST['kvm_admin_email_recipients'])));
            }

            if (isset($_POST['kvm_email_templates']) && is_array($_POST['kvm_email_templates'])) {
                $sanitized_templates = array();
                foreach (wp_unslash($_POST['kvm_email_templates']) as $event_key => $event_cfg) {
                    $clean_key = sanitize_key($event_key);
                    if (empty($clean_key) || !is_array($event_cfg)) {
                        continue;
                    }
                    $sanitized_templates[$clean_key] = array(
                        'enabled'   => !empty($event_cfg['enabled']) ? '1' : '0',
                        'recipient' => isset($event_cfg['recipient']) ? sanitize_text_field($event_cfg['recipient']) : '',
                        'subject'   => isset($event_cfg['subject']) ? sanitize_text_field($event_cfg['subject']) : '',
                        'body'      => isset($event_cfg['body']) ? sanitize_textarea_field($event_cfg['body']) : '',
                    );
                }
                update_option('kvm_email_templates', $sanitized_templates);
            }

            $this->redirect(array('tab' => 'emails', 'saved' => '1'));
        }

        /* --- Global settings (canonical kvm_ options) -------------------- */
        if (isset($_POST['kvm_save_all_settings'])) {
            $this->verify_nonce('kvm_all_settings_action', 'kvm_all_settings_nonce');

            // Protecting data with a wipe requires the installation passkey.
            $wipe_enabled = isset($_POST['kvm_delete_data_on_uninstall']);
            if ($wipe_enabled) {
                $gate = KeyVault_Manager_Gate::get_instance();
                $passkey = isset($_POST['kvm_wipe_passkey']) ? wp_unslash($_POST['kvm_wipe_passkey']) : '';
                if (!$gate->verify_passkey($passkey)) {
                    $this->redirect(array('tab' => 'settings', 'wipe_error' => '1'));
                }
            }

            KeyVault_Manager_DB::update_opt('price_starter', floatval(isset($_POST['kvm_price_starter']) ? wp_unslash($_POST['kvm_price_starter']) : 49));
            KeyVault_Manager_DB::update_opt('starter_max', max(1, intval(isset($_POST['kvm_starter_max']) ? wp_unslash($_POST['kvm_starter_max']) : 9)));
            KeyVault_Manager_DB::update_opt('price_professional', floatval(isset($_POST['kvm_price_professional']) ? wp_unslash($_POST['kvm_price_professional']) : 39));
            KeyVault_Manager_DB::update_opt('pro_max', max(1, intval(isset($_POST['kvm_pro_max']) ? wp_unslash($_POST['kvm_pro_max']) : 99)));
            KeyVault_Manager_DB::update_opt('price_enterprise', floatval(isset($_POST['kvm_price_enterprise']) ? wp_unslash($_POST['kvm_price_enterprise']) : 30));
            KeyVault_Manager_DB::update_opt('grace_period_days', min(365, max(0, intval(isset($_POST['kvm_grace_period_days']) ? wp_unslash($_POST['kvm_grace_period_days']) : 7))));
            KeyVault_Manager_DB::update_opt('zip_file_path', sanitize_text_field(wp_unslash(isset($_POST['kvm_zip_file_path']) ? $_POST['kvm_zip_file_path'] : '')));
            KeyVault_Manager_DB::update_opt('client_plugin_slug', sanitize_title(wp_unslash(isset($_POST['kvm_client_plugin_slug']) ? $_POST['kvm_client_plugin_slug'] : 'client-plugin')));
            KeyVault_Manager_DB::update_opt('client_version', sanitize_text_field(wp_unslash(isset($_POST['kvm_client_version']) ? $_POST['kvm_client_version'] : '1.0.0')));

            $color = sanitize_hex_color(wp_unslash(isset($_POST['kvm_primary_color']) ? $_POST['kvm_primary_color'] : '#2563eb'));
            KeyVault_Manager_DB::update_opt('primary_color', $color ? $color : '#2563eb');
            KeyVault_Manager_DB::update_opt('custom_login_url', esc_url_raw(wp_unslash(isset($_POST['kvm_custom_login_url']) ? $_POST['kvm_custom_login_url'] : '')));
            KeyVault_Manager_DB::update_opt('delete_data_on_uninstall', $wipe_enabled ? 1 : 0);

            $this->redirect(array('tab' => 'settings', 'saved' => '1'));
        }

        /* --- Payment gateway settings ------------------------------------ */
        if (isset($_POST['kvm_save_gateway_settings'])) {
            $this->verify_nonce('kvm_gateway_settings_action', 'kvm_gateway_settings_nonce');

            $env = (isset($_POST['kvm_environment']) && 'live' === $_POST['kvm_environment']) ? 'live' : 'sandbox';
            update_option('kvm_environment', $env);
            update_option('kvm_enable_nonce', isset($_POST['kvm_enable_nonce']) ? 1 : 0);

            $api_settings = array(
                'stripe_publishable_test' => isset($_POST['kvm_stripe_publishable_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_publishable_test'])) : '',
                'stripe_secret_test'      => isset($_POST['kvm_stripe_secret_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_secret_test'])) : '',
                'stripe_publishable_live' => isset($_POST['kvm_stripe_publishable_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_publishable_live'])) : '',
                'stripe_secret_live'      => isset($_POST['kvm_stripe_secret_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_secret_live'])) : '',
                'stripe_webhook_secret'   => isset($_POST['kvm_stripe_webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_webhook_secret'])) : '',
                'paypal_client_test'      => isset($_POST['kvm_paypal_client_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_client_test'])) : '',
                'paypal_secret_test'      => isset($_POST['kvm_paypal_secret_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_secret_test'])) : '',
                'paypal_client_live'      => isset($_POST['kvm_paypal_client_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_client_live'])) : '',
                'paypal_secret_live'      => isset($_POST['kvm_paypal_secret_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_secret_live'])) : '',
                'paypal_plan_id'          => isset($_POST['kvm_paypal_plan_id']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_plan_id'])) : '',
            );
            update_option('kvm_api_settings', $api_settings);
            $this->redirect(array('tab' => 'gateways', 'saved' => '1'));
        }

        /* --- Bulk license actions (set-based queries) --------------------- */
        if (isset($_POST['kvm_bulk_license_action'])) {
            $this->verify_nonce('kvm_bulk_license_action', 'kvm_bulk_license_nonce');

            $action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
            $ids    = array_filter(array_map('intval', (array) (isset($_POST['license_ids']) ? $_POST['license_ids'] : array())));

            if (!empty($action) && !empty($ids)) {
                global $wpdb;
                $in  = implode(',', array_map('intval', $ids));
                $lic = KeyVault_Manager_DB::table('licenses');
                $act = KeyVault_Manager_DB::table('activations');

                if ($action === 'delete') {
                    $wpdb->query("DELETE FROM $act WHERE license_id IN ($in) OR license_id IN (SELECT id FROM $lic WHERE parent_id IN ($in))");
                    $wpdb->query("DELETE FROM $lic WHERE parent_id IN ($in)");
                    $wpdb->query("DELETE FROM $lic WHERE id IN ($in)");
                } elseif ($action === 'revoke') {
                    $wpdb->query("UPDATE $lic SET status = 'revoked' WHERE id IN ($in)");
                    $wpdb->query("DELETE FROM $act WHERE license_id IN ($in)");
                } elseif ($action === 'activate') {
                    $wpdb->query("UPDATE $lic SET status = 'active' WHERE id IN ($in) AND status != 'parent_holder'");
                }
            }
            $this->redirect(array('tab' => 'licenses', 'bulk_processed' => '1'));
        }

        /* --- Single row actions ------------------------------------------- */
        if (isset($_GET['action']) && isset($_GET['id'])) {
            $license_id = intval($_GET['id']);
            $action     = sanitize_key(wp_unslash($_GET['action']));

            if ($license_id > 0 && in_array($action, array('revoke', 'activate', 'delete_key'), true) && check_admin_referer($action . '_key_' . $license_id)) {
                global $wpdb;
                $lic = KeyVault_Manager_DB::table('licenses');

                $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lic WHERE id = %d", $license_id));

                if ($action === 'revoke') {
                    $wpdb->update($lic, array('status' => 'revoked'), array('id' => $license_id));
                    $wpdb->delete(KeyVault_Manager_DB::table('activations'), array('license_id' => $license_id));
                    if ($license) {
                        KeyVault_Manager_Emails::get_instance()->send_event_email('user_license_status', $license->customer_email, array(
                            'customer_name'  => $license->customer_name,
                            'customer_email' => $license->customer_email,
                            'license_keys'   => $license->license_key,
                        ));
                    }
                } elseif ($action === 'activate') {
                    $wpdb->update($lic, array('status' => 'active'), array('id' => $license_id));
                    if ($license) {
                        KeyVault_Manager_Emails::get_instance()->send_event_email('user_license_status', $license->customer_email, array(
                            'customer_name'  => $license->customer_name,
                            'customer_email' => $license->customer_email,
                            'license_keys'   => $license->license_key,
                        ));
                    }
                } elseif ($action === 'delete_key') {
                    $db->delete_license_and_activations($license_id);
                }
                $notice_key = ($action === 'delete_key') ? 'deleted' : 'row_updated';
                $this->redirect(array('tab' => 'licenses', $notice_key => '1'));
            }
        }

        if (isset($_GET['action']) && $_GET['action'] === 'admin_unlink_instant' && isset($_GET['act_id'])) {
            $act_id = intval($_GET['act_id']);
            if ($act_id > 0 && check_admin_referer('admin_unlink_' . $act_id)) {
                global $wpdb;
                $wpdb->delete(KeyVault_Manager_DB::table('activations'), array('id' => $act_id));
                $this->redirect(array('tab' => 'licenses', 'row_updated' => '1'));
            }
        }

        /* --- Lock the hub again (replaces the old debug URL) -------------- */
        if (isset($_POST['kvm_lock_hub'])) {
            $this->verify_nonce('kvm_lock_hub_action', 'kvm_lock_hub_nonce');
            update_option('kvm_plugin_unlocked', false);
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
            exit;
        }
    }

    /* ------------------------------------------------------------------
     * Page shell
     * ---------------------------------------------------------------- */

    public function render_page() {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'licenses';
        if (!in_array($tab, array('licenses', 'orders', 'users', 'emails', 'gateways', 'settings', 'logs'), true)) {
            $tab = 'licenses';
        }

        $flash = $this->take_flash();
        ?>
        <div class="kvm-hub-wrapper">
            <div class="kvm-header-banner">
                <div class="kvm-header-brand">
                    <span class="kvm-header-logo" aria-hidden="true">🔐</span>
                    <div>
                        <h1 class="kvm-header-title"><?php echo esc_html(kvm_brand_name()); ?> <span class="kvm-version-chip">v<?php echo esc_html(KVM_VERSION); ?></span></h1>
                        <p class="kvm-header-subtitle">Self-hosted SaaS licensing hub &amp; customer portal engine</p>
                    </div>
                </div>
            </div>

            <?php if ($flash) : ?>
                <div class="kvm-flash" id="kvm-flash">
                    <div class="kvm-flash-text">
                        <strong><?php echo esc_html($flash['message']); ?></strong>
                        <?php if (!empty($flash['keys'])) : ?>
                            <div class="kvm-flash-keys">
                                <?php foreach ($flash['keys'] as $key) : ?>
                                    <code class="kvm-code kvm-flash-key"><?php echo esc_html($key); ?></code>
                                    <button type="button" class="button button-small kvm-copy-btn" data-kvm-copy="<?php echo esc_attr($key); ?>">Copy</button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="kvm-flash-dismiss" data-kvm-dismiss>&times;</button>
                </div>
            <?php endif; ?>

            <nav class="kvm-nav">
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=licenses')); ?>" class="kvm-nav-btn<?php echo $tab === 'licenses' ? ' is-active' : ''; ?>">🔑 License Hub</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=orders')); ?>" class="kvm-nav-btn<?php echo $tab === 'orders' ? ' is-active' : ''; ?>">🛒 Orders</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=users')); ?>" class="kvm-nav-btn<?php echo $tab === 'users' ? ' is-active' : ''; ?>">👤 Users</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=emails')); ?>" class="kvm-nav-btn<?php echo $tab === 'emails' ? ' is-active' : ''; ?>">✉️ Emails</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=gateways')); ?>" class="kvm-nav-btn<?php echo $tab === 'gateways' ? ' is-active' : ''; ?>">💳 Gateways</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=settings')); ?>" class="kvm-nav-btn<?php echo $tab === 'settings' ? ' is-active' : ''; ?>">⚙️ Settings</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=logs')); ?>" class="kvm-nav-btn<?php echo $tab === 'logs' ? ' is-active' : ''; ?>">📜 Logs</a>
            </nav>
            <?php
            $views_dir = KVM_PATH . 'admin/views/';
            switch ($tab) {
                case 'orders':
                    include $views_dir . 'orders.php';
                    break;
                case 'users':
                    include $views_dir . 'users.php';
                    break;
                case 'emails':
                    include $views_dir . 'emails.php';
                    break;
                case 'gateways':
                    include $views_dir . 'gateways.php';
                    break;
                case 'settings':
                    include $views_dir . 'settings.php';
                    break;
                case 'logs':
                    include $views_dir . 'logs.php';
                    break;
                default:
                    include $views_dir . 'licenses-list.php';
            }
            ?>
        </div>

        <?php // Shared modal shells (populated by JS — no per-row modal duplication). ?>
        <div class="kvm-modal-overlay" id="kvm-details-modal">
            <div class="kvm-modal-box">
                <div class="kvm-modal-header">
                    <h3 class="kvm-modal-title">License Management &amp; Connected Domains</h3>
                    <button type="button" class="kvm-modal-close" data-kvm-close="kvm-details-modal">&times;</button>
                </div>

                <div class="kvm-modal-info-card">
                    <p class="kvm-modal-p"><strong>License Key:</strong> <code class="kvm-code" id="kvm-detail-key"></code>
                        <button type="button" class="button button-small kvm-copy-btn" id="kvm-detail-copy">Copy</button>
                    </p>
                    <p class="kvm-modal-p"><strong>Customer:</strong> <span id="kvm-detail-customer"></span></p>
                    <p class="kvm-modal-p"><strong>Plan Tier:</strong> <span id="kvm-detail-plan"></span></p>
                    <p class="kvm-modal-p"><strong>Quota:</strong> <span id="kvm-detail-quota"></span></p>
                    <p class="kvm-modal-p"><strong>Expiration:</strong> <span id="kvm-detail-expiry"></span></p>
                    <p class="kvm-modal-p last"><strong>Status:</strong> <span id="kvm-detail-status"></span></p>
                </div>

                <div class="kvm-modal-actions-bar">
                    <span class="kvm-action-label">Key Actions:</span>
                    <div class="kvm-action-btns">
                        <button type="button" class="button button-small" id="kvm-detail-edit-expiry">📅 Edit Expiry</button>
                        <a href="#" class="button button-small kvm-revoke-btn" id="kvm-detail-toggle-status">Revoke Key</a>
                    </div>
                </div>

                <h4 class="kvm-domains-heading">Connected Websites / Domains</h4>
                <div class="kvm-domains-list" id="kvm-detail-domains"></div>

                <div class="kvm-modal-footer">
                    <a href="#" class="button kvm-delete-key-btn" id="kvm-detail-delete">Delete Key Permanently</a>
                    <button type="button" class="button button-primary" data-kvm-close="kvm-details-modal">Close</button>
                </div>
            </div>
        </div>

        <div class="kvm-modal-overlay" id="kvm-expiry-modal">
            <div class="kvm-modal-box compact">
                <div class="kvm-modal-header">
                    <h3 class="kvm-modal-title">Edit Expiration Date</h3>
                    <button type="button" class="kvm-modal-close" data-kvm-close="kvm-expiry-modal">&times;</button>
                </div>
                <p class="kvm-modal-sub">License Key: <code class="kvm-code" id="kvm-expiry-key"></code></p>

                <form method="post" action="">
                    <?php wp_nonce_field('kvm_update_expiry_action', 'kvm_update_expiry_nonce'); ?>
                    <input type="hidden" name="license_id" id="kvm-expiry-license-id" value="">

                    <div class="kvm-form-group">
                        <label class="kvm-form-label">Expiration Rule:</label>
                        <select name="expiry_type" class="kvm-select" id="kvm-expiry-rule">
                            <option value="extend_1y">Extend by +1 Year</option>
                            <option value="lifetime">Set to Lifetime (No Expiry)</option>
                            <option value="custom">Set Specific Custom Date</option>
                        </select>
                    </div>

                    <div class="kvm-form-group" id="kvm-expiry-date-wrap" style="display:none;">
                        <label class="kvm-form-label">Specific Expiration Date:</label>
                        <input type="date" name="custom_expiry_date" class="kvm-input" id="kvm-expiry-date" value="">
                    </div>

                    <div class="kvm-modal-footer right">
                        <button type="button" class="button" data-kvm-close="kvm-expiry-modal">Cancel</button>
                        <button type="submit" name="kvm_update_expiry" class="kvm-btn kvm-btn--primary compact">Save Expiry</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="kvm-modal-overlay" id="kvm-delete-user-modal">
            <div class="kvm-modal-box compact">
                <div class="kvm-modal-header">
                    <h3 class="kvm-modal-title">Delete User Account Options</h3>
                    <button type="button" class="kvm-modal-close" data-kvm-close="kvm-delete-user-modal">&times;</button>
                </div>
                <form method="post">
                    <?php wp_nonce_field('kvm_delete_user_choice', 'kvm_delete_user_choice_nonce'); ?>
                    <input type="hidden" id="kvm_del_user_id" name="user_id">
                    <p class="kvm-modal-sub">Select a deletion action for <strong id="kvm_del_user_name"></strong>:</p>

                    <div class="kvm-choice-stack">
                        <label class="kvm-choice kvm-choice--neutral">
                            <input type="radio" name="delete_type" value="keys_only" checked>
                            <span>
                                <strong>Delete Only All Keys</strong>
                                <small>Removes all issued keys &amp; domain activations, keeping the user account.</small>
                            </span>
                        </label>
                        <label class="kvm-choice kvm-choice--danger">
                            <input type="radio" name="delete_type" value="user_and_keys">
                            <span>
                                <strong>Delete All Keys + Delete User Account</strong>
                                <small>Permanently deletes the user account along with all associated keys &amp; domains.</small>
                            </span>
                        </label>
                    </div>

                    <button type="submit" name="kvm_delete_user_choice_action" class="kvm-btn kvm-btn--danger kvm-btn--block">Confirm &amp; Delete</button>
                </form>
            </div>
        </div>

        <div class="kvm-modal-overlay" id="kvm-edit-user-modal">
            <div class="kvm-modal-box compact">
                <div class="kvm-modal-header">
                    <h3 class="kvm-modal-title">Edit User Information</h3>
                    <button type="button" class="kvm-modal-close" data-kvm-close="kvm-edit-user-modal">&times;</button>
                </div>
                <form method="post">
                    <?php wp_nonce_field('kvm_update_user_action', 'kvm_update_user_nonce'); ?>
                    <input type="hidden" id="kvm_edit_user_id" name="user_id">

                    <div class="kvm-form-group">
                        <label class="kvm-form-label" for="kvm_edit_user_name">Full Name:</label>
                        <input type="text" id="kvm_edit_user_name" name="user_name" class="kvm-input" required>
                    </div>

                    <div class="kvm-form-group">
                        <label class="kvm-form-label" for="kvm_edit_user_email">Email Address:</label>
                        <input type="email" id="kvm_edit_user_email" name="user_email" class="kvm-input" required>
                    </div>

                    <div class="kvm-form-group">
                        <label class="kvm-form-label" for="kvm_edit_user_password">Set New Password (leave blank to keep existing):</label>
                        <input type="text" id="kvm_edit_user_password" name="user_password" class="kvm-input" placeholder="Enter a new password" autocomplete="new-password">
                    </div>

                    <button type="submit" name="kvm_update_user" class="kvm-btn kvm-btn--primary kvm-btn--block">Save User Changes</button>
                </form>
            </div>
        </div>

        <div class="kvm-modal-overlay" id="kvm-order-modal">
            <div class="kvm-modal-box">
                <div class="kvm-modal-header">
                    <h3 class="kvm-modal-title">Order Details: <span id="modal-order-id" class="kvm-accent-text"></span></h3>
                    <button type="button" class="kvm-modal-close" data-kvm-close="kvm-order-modal">&times;</button>
                </div>

                <div class="kvm-modal-info-card kvm-detail-grid">
                    <p class="kvm-modal-p"><strong>Customer Name:</strong><br><span id="modal-name"></span></p>
                    <p class="kvm-modal-p"><strong>Email Address:</strong><br><span id="modal-email"></span></p>
                    <p class="kvm-modal-p"><strong>Total Paid:</strong><br><span id="modal-amount" class="kvm-good-text"></span></p>
                    <p class="kvm-modal-p"><strong>Payment Gateway:</strong><br><span id="modal-gateway"></span></p>
                    <p class="kvm-modal-p"><strong>Website Slots:</strong><br><span id="modal-qty"></span></p>
                    <p class="kvm-modal-p"><strong>License Mode:</strong><br><span id="modal-mode"></span></p>
                </div>

                <div class="kvm-form-group">
                    <label class="kvm-form-label">Generated License Keys:</label>
                    <textarea id="modal-keys" readonly class="kvm-textarea" rows="4"></textarea>
                </div>

                <div class="kvm-modal-footer right">
                    <button type="button" class="button button-primary" data-kvm-close="kvm-order-modal">Close Window</button>
                </div>
            </div>
        </div>
        <?php
    }
}
