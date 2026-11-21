<?php
if (!defined('ABSPATH')) { exit; }

class KeyVault_Manager_Admin {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets($hook) {
        if ('toplevel_page_keyvault-manager' !== $hook) {
            return;
        }
        wp_enqueue_style('kvm-admin-style', KVM_URL . 'admin/assets/css/admin-style.css', array(), KVM_VERSION);
        wp_enqueue_script('kvm-admin-script', KVM_URL . 'admin/assets/js/admin-script.js', array('jquery'), KVM_VERSION, true);
    }
    
    public function register_menu() {
        add_menu_page('KeyVault Manager for CDF', 'KeyVault Hub', 'manage_options', 'keyvault-manager', array($this, 'render_page'), 'dashicons-performance', 30);
    }
    
    public function handle_actions() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $slug = get_option('kvm_plugin_slug', KVM_SLUG);
        $table_licenses = $wpdb->prefix . $slug . '_licenses';
        $table_activations = $wpdb->prefix . $slug . '_activations';

        if (isset($_POST['kvm_generate_key']) && check_admin_referer('kvm_gen_action', 'kvm_gen_nonce')) {
            $user_id = intval($_POST['user_id']);
            if ($user_id > 0) {
                $expiry_choice = sanitize_text_field($_POST['expiry_choice'] ?? 'standard');
                $is_lifetime = ($expiry_choice === 'lifetime');
                
                KeyVault_Manager_DB::get_instance()->generate_license_keys(
                    '',
                    '',
                    intval($_POST['custom_sites']),
                    sanitize_text_field($_POST['license_mode']),
                    $is_lifetime,
                    sanitize_textarea_field($_POST['notes']),
                    $user_id,
                    $_POST['custom_expires_at'] ?? null
                );
            }
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&created=1'));
            exit;
        }

        if (isset($_POST['kvm_update_expiry']) && check_admin_referer('kvm_update_expiry_action', 'kvm_update_expiry_nonce')) {
            $license_id = intval($_POST['license_id'] ?? 0);
            $expiry_type = sanitize_text_field($_POST['expiry_type'] ?? 'extend_1y');
            $custom_date = sanitize_text_field($_POST['custom_expiry_date'] ?? '');

            KeyVault_Manager_DB::get_instance()->update_expiration($license_id, $expiry_type, $custom_date);
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&expiry_updated=1'));
            exit;
        }

        if (isset($_POST['kvm_create_user']) && check_admin_referer('kvm_create_user_action', 'kvm_create_user_nonce')) {
            KeyVault_Manager_User_Auth::get_instance()->get_or_create_user(
                sanitize_text_field($_POST['user_name']),
                sanitize_email($_POST['user_email']),
                sanitize_text_field($_POST['user_password'] ?? '')
            );
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=users&user_created=1'));
            exit;
        }

        if (isset($_POST['kvm_update_user']) && check_admin_referer('kvm_update_user_action', 'kvm_update_user_nonce')) {
            $user_id = intval($_POST['user_id']);
            $user_name = sanitize_text_field($_POST['user_name']);
            $user_email = sanitize_email($_POST['user_email']);
            $user_password = sanitize_text_field($_POST['user_password'] ?? '');

            KeyVault_Manager_User_Auth::get_instance()->update_user_info($user_id, $user_name, $user_email, $user_password);
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=users&user_updated=1'));
            exit;
        }

        if (isset($_POST['kvm_delete_user_choice_action']) && check_admin_referer('kvm_delete_user_choice', 'kvm_delete_user_choice_nonce')) {
            $user_id = intval($_POST['user_id']);
            $choice = sanitize_text_field($_POST['delete_type']);
            if ($choice === 'keys_only') {
                KeyVault_Manager_DB::get_instance()->delete_user_keys_only($user_id);
            } else {
                KeyVault_Manager_DB::get_instance()->delete_user_and_all_licenses($user_id);
            }
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=users&deleted=1'));
            exit;
        }

        // Email Tab Settings Handler (uses kvm_all_settings_action from admin/views/emails.php)
        if (isset($_POST['kvm_save_all_settings']) && !isset($_POST[$slug . '_save_all_settings']) && check_admin_referer('kvm_all_settings_action', 'kvm_all_settings_nonce')) {
            if (isset($_POST['kvm_email_from_name'])) {
                update_option('kvm_email_from_name', sanitize_text_field(wp_unslash($_POST['kvm_email_from_name'])));
            }
            if (isset($_POST['kvm_email_from_email'])) {
                update_option('kvm_email_from_email', sanitize_email(wp_unslash($_POST['kvm_email_from_email'])));
            }
            if (isset($_POST['kvm_email_reply_to'])) {
                update_option('kvm_email_reply_to', sanitize_email(wp_unslash($_POST['kvm_email_reply_to'])));
            }
            if (isset($_POST['kvm_admin_email_recipients'])) {
                update_option('kvm_admin_email_recipients', sanitize_text_field(wp_unslash($_POST['kvm_admin_email_recipients'])));
            }

            if (isset($_POST['kvm_email_templates']) && is_array($_POST['kvm_email_templates'])) {
                $sanitized_templates = array();
                foreach ($_POST['kvm_email_templates'] as $event_key => $event_cfg) {
                    $clean_key = sanitize_key($event_key);
                    if (empty($clean_key) || !is_array($event_cfg)) {
                        continue;
                    }
                    $sanitized_templates[$clean_key] = array(
                        'enabled'   => !empty($event_cfg['enabled']) ? '1' : '0',
                        'recipient' => isset($event_cfg['recipient']) ? sanitize_text_field(wp_unslash($event_cfg['recipient'])) : '',
                        'subject'   => isset($event_cfg['subject']) ? sanitize_text_field(wp_unslash($event_cfg['subject'])) : '',
                        'body'      => isset($event_cfg['body']) ? sanitize_textarea_field(wp_unslash($event_cfg['body'])) : '',
                    );
                }
                update_option('kvm_email_templates', $sanitized_templates);
            }

            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=emails&saved=1'));
            exit;
        }

        // Updated Global SaaS Settings Handler matching dynamic $slug
        if (isset($_POST[$slug . '_save_all_settings']) && check_admin_referer($slug . '_all_settings_action', $slug . '_all_settings_nonce')) {
            update_option($slug . '_price_starter', sanitize_text_field($_POST[$slug . '_price_starter']));
            update_option($slug . '_starter_max', intval($_POST[$slug . '_starter_max']));
            update_option($slug . '_price_professional', sanitize_text_field($_POST[$slug . '_price_professional']));
            update_option($slug . '_pro_max', intval($_POST[$slug . '_pro_max']));
            update_option($slug . '_price_enterprise', sanitize_text_field($_POST[$slug . '_price_enterprise']));
            update_option($slug . '_grace_period_days', intval($_POST[$slug . '_grace_period_days']));
            update_option($slug . '_zip_file_path', sanitize_text_field($_POST[$slug . '_zip_file_path']));
            update_option($slug . '_primary_color', sanitize_hex_color($_POST[$slug . '_primary_color']) ?: '#2563eb');
            update_option($slug . '_custom_login_url', esc_url_raw($_POST[$slug . '_custom_login_url']));
            update_option($slug . '_delete_data_on_uninstall', isset($_POST[$slug . '_delete_data_on_uninstall']) ? 1 : 0);

            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=settings&saved=1'));
            exit;
        }
		
		// Payment Gateway Settings Save Handler
        if (isset($_POST['kvm_save_gateway_settings']) && check_admin_referer('kvm_gateway_settings_action', 'kvm_gateway_settings_nonce')) {
            $env = isset($_POST['kvm_environment']) && 'live' === $_POST['kvm_environment'] ? 'live' : 'sandbox';
            update_option('kvm_environment', $env);
            
            $enable_nonce = isset($_POST['kvm_enable_nonce']) ? 1 : 0;
            update_option('kvm_enable_nonce', $enable_nonce);
            
            $api_settings = array(
                'stripe_publishable_test' => isset($_POST['kvm_stripe_publishable_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_publishable_test'])) : '',
                'stripe_secret_test'      => isset($_POST['kvm_stripe_secret_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_secret_test'])) : '',
                'stripe_publishable_live' => isset($_POST['kvm_stripe_publishable_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_publishable_live'])) : '',
                'stripe_secret_live'      => isset($_POST['kvm_stripe_secret_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_stripe_secret_live'])) : '',
                'paypal_client_test'      => isset($_POST['kvm_paypal_client_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_client_test'])) : '',
                'paypal_secret_test'      => isset($_POST['kvm_paypal_secret_test']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_secret_test'])) : '',
                'paypal_client_live'      => isset($_POST['kvm_paypal_client_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_client_live'])) : '',
                'paypal_secret_live'      => isset($_POST['kvm_paypal_secret_live']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_secret_live'])) : '',
                'paypal_plan_id'          => isset($_POST['kvm_paypal_plan_id']) ? sanitize_text_field(wp_unslash($_POST['kvm_paypal_plan_id'])) : '',
            );
            update_option('kvm_api_settings', $api_settings);
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=gateways&saved=1'));
            exit;
        }
			
        if (isset($_POST['kvm_bulk_license_action']) && check_admin_referer('kvm_bulk_license_action', 'kvm_bulk_license_nonce')) {
            $action = sanitize_text_field($_POST['bulk_action']);
            $selected_ids = array_map('intval', $_POST['license_ids'] ?? array());
            if (!empty($action) && !empty($selected_ids)) {
                foreach ($selected_ids as $id) {
                    if ($action === 'delete') {
                        KeyVault_Manager_DB::get_instance()->delete_license_and_activations($id);
                    } elseif ($action === 'revoke') {
                        $wpdb->update($table_licenses, array('status' => 'revoked'), array('id' => $id));
                        $wpdb->delete($table_activations, array('license_id' => $id));
                    } elseif ($action === 'activate') {
                        $wpdb->update($table_licenses, array('status' => 'active'), array('id' => $id));
                    }
                }
            }
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&bulk_processed=1'));
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'admin_unlink_instant' && isset($_GET['act_id']) && check_admin_referer('admin_unlink_' . intval($_GET['act_id']))) {
            $wpdb->delete($table_activations, array('id' => intval($_GET['act_id'])));
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['id']) && check_admin_referer('revoke_key_' . intval($_GET['id']))) {
            $wpdb->update($table_licenses, array('status' => 'revoked'), array('id' => intval($_GET['id'])));
            $wpdb->delete($table_activations, array('license_id' => intval($_GET['id'])));
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['id']) && check_admin_referer('activate_key_' . intval($_GET['id']))) {
            $wpdb->update($table_licenses, array('status' => 'active'), array('id' => intval($_GET['id'])));
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager'));
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_key' && isset($_GET['id']) && check_admin_referer('delete_key_' . intval($_GET['id']))) {
            KeyVault_Manager_DB::get_instance()->delete_license_and_activations(intval($_GET['id']));
            wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&deleted=1'));
            exit;
        }
    }

    public function render_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'licenses';
        ?>
        <div class="kvm-hub-wrapper">
            <div class="kvm-header-banner">
                <div>
                    <h1 class="kvm-header-title">⚡ KeyVault Manager for CDF v5.7.6 Enterprise</h1>
                    <p class="kvm-header-subtitle">Self-Hosted High Performance SaaS Licensing Hub & User Portal Engine</p>
                </div>
            </div>

            <div class="kvm-nav-container">
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=licenses'); ?>" class="kvm-nav-btn <?php echo $tab === 'licenses' ? 'active' : ''; ?>">🔑 License Control Hub</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=orders'); ?>" class="kvm-nav-btn <?php echo $tab === 'orders' ? 'active' : ''; ?>">🛒 Orders</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=users'); ?>" class="kvm-nav-btn <?php echo $tab === 'users' ? 'active' : ''; ?>">👤 User Management</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=emails'); ?>" class="kvm-nav-btn <?php echo $tab === 'emails' ? 'active' : ''; ?>">✉️ Emails</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=gateways'); ?>" class="kvm-nav-btn <?php echo $tab === 'gateways' ? 'active' : ''; ?>">💳 Payment Gateways</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=settings'); ?>" class="kvm-nav-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager&tab=logs'); ?>" class="kvm-nav-btn <?php echo $tab === 'logs' ? 'active' : ''; ?>">📜 Logs</a>
            </div>
            <?php
            $views_dir = KVM_PATH . 'admin/views/';
            if ($tab === 'orders') {
                include $views_dir . 'orders.php';
            } elseif ($tab === 'users') {
                include $views_dir . 'users.php';
            } elseif ($tab === 'emails') {
                include $views_dir . 'emails.php';
            } elseif ($tab === 'gateways') {
                include $views_dir . 'gateways.php';
            } elseif ($tab === 'settings') {
                include $views_dir . 'settings.php';
            } elseif ($tab === 'logs') {
                include $views_dir . 'logs.php';
            } else {
                include $views_dir . 'licenses-list.php';
            }
            ?>
        </div>

        <div id="kvm-delete-user-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999999; align-items:center; justify-content:center;">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; width:100%; max-width:500px; padding:35px; color:#0f172a; box-shadow:0 25px 50px rgba(0,0,0,0.25); position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Delete User Account Options</h3>
                    <button type="button" onclick="document.getElementById('kvm-delete-user-modal').style.display='none'" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer; font-weight:bold;">&times;</button>
                </div>
                <form method="post">
                    <?php wp_nonce_field('kvm_delete_user_choice', 'kvm_delete_user_choice_nonce'); ?>
                    <input type="hidden" id="kvm_del_user_id" name="user_id">
                    <p style="color:#64748b; font-size:14px; margin-top:0;">Please select which deletion action you wish to perform for <strong id="kvm_del_user_name" style="color:#0f172a;"></strong>:</p>
                    
                    <div style="margin-bottom:25px; display:flex; flex-direction:column; gap:12px;">
                        <label style="display:flex; align-items:flex-start; gap:10px; padding:14px 18px; border:1.5px solid #e2e8f0; border-radius:12px; cursor:pointer; background:#f8fafc;">
                            <input type="radio" name="delete_type" value="keys_only" checked style="margin-top:3px;">
                            <div>
                                <strong style="color:#0f172a; font-size:14px; display:block;">Delete Only All Keys</strong>
                                <span style="color:#64748b; font-size:12px;">Removes all issued keys & domain activations, keeping the user account.</span>
                            </div>
                        </label>
                        <label style="display:flex; align-items:flex-start; gap:10px; padding:14px 18px; border:1.5px solid #fee2e2; border-radius:12px; cursor:pointer; background:#fff5f5;">
                            <input type="radio" name="delete_type" value="user_and_keys" style="margin-top:3px;">
                            <div>
                                <strong style="color:#991b1b; font-size:14px; display:block;">Delete All Keys + Delete User Account</strong>
                                <span style="color:#991b1b; font-size:12px;">Permanently deletes the user account along with all associated keys & domains.</span>
                            </div>
                        </label>
                    </div>

                    <button type="submit" name="kvm_delete_user_choice_action" class="kvm-btn-submit" style="width:100%; background:#ef4444;">Confirm & Delete</button>
                </form>
            </div>
        </div>

        <div id="kvm-edit-user-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999999; align-items:center; justify-content:center;">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; width:100%; max-width:500px; padding:35px; color:#0f172a; box-shadow:0 25px 50px rgba(0,0,0,0.25); position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Edit User Information</h3>
                    <button type="button" onclick="document.getElementById('kvm-edit-user-modal').style.display='none'" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer; font-weight:bold;">&times;</button>
                </div>
                <form method="post">
                    <?php wp_nonce_field('kvm_update_user_action', 'kvm_update_user_nonce'); ?>
                    <input type="hidden" id="kvm_edit_user_id" name="user_id">
                    
                    <div style="margin-bottom:18px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; color:#475569;">Full Name:</label>
                        <input type="text" id="kvm_edit_user_name" name="user_name" class="kvm-input" required>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; color:#475569;">Email Address:</label>
                        <input type="email" id="kvm_edit_user_email" name="user_email" class="kvm-input" required>
                    </div>

                    <div style="margin-bottom:25px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; color:#475569;">New Password (Leave blank to keep existing):</label>
                        <input type="text" id="kvm_edit_user_password" name="user_password" class="kvm-input" placeholder="Enter new password">
                    </div>

                    <button type="submit" name="kvm_update_user" class="kvm-btn-submit" style="width:100%;">Save User Changes</button>
                </form>
            </div>
        </div>
        <?php
    }
}