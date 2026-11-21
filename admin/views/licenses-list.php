<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 100;
$offset = ($current_page - 1) * $per_page;

$slug = get_option('kvm_plugin_slug', KVM_SLUG);
$table_licenses = $wpdb->prefix . $slug . '_licenses';
$table_users = $wpdb->prefix . $slug . '_users';
$table_activations = $wpdb->prefix . $slug . '_activations';

// Load configured slot range boundaries so license rows match order form plan tiers
$starter_max = intval(get_option($slug . '_starter_max', '9'));
$pro_max     = intval(get_option($slug . '_pro_max', '99'));

if (!empty($search)) {
    $total_items = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_licenses WHERE license_key LIKE %s OR customer_email LIKE %s OR customer_name LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%')));
    $licenses = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_licenses WHERE license_key LIKE %s OR customer_email LIKE %s OR customer_name LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%', $per_page, $offset));
} else {
    $total_items = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses"));
    $licenses = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_licenses ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
}

$total_pages = ceil($total_items / $per_page);

$users = $wpdb->get_results("SELECT * FROM $table_users ORDER BY name ASC");

$total_users_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_users"));
$total_keys_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status != 'parent_holder'"));
$active_keys_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'active'"));
$revoked_keys_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'revoked'"));
$total_linked_sites = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_activations"));
?>

<div class="kvm-stats-grid">
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Registered Users</span>
        <h3 class="kvm-stat-val"><?php echo $total_users_count; ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Total License Keys</span>
        <h3 class="kvm-stat-val primary"><?php echo $total_keys_count; ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Active Keys</span>
        <h3 class="kvm-stat-val success"><?php echo $active_keys_count; ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Revoked Keys</span>
        <h3 class="kvm-stat-val danger"><?php echo $revoked_keys_count; ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Linked Websites</span>
        <h3 class="kvm-stat-val purple"><?php echo $total_linked_sites; ?></h3>
    </div>
</div>

<div class="kvm-card-panel">
    <h3>Issue New SaaS License Key</h3>
    <form method="post" class="kvm-issue-form">
        <?php wp_nonce_field('kvm_gen_action', 'kvm_gen_nonce'); ?>
        <div>
            <label class="kvm-form-label">Select User Account:</label>
            <select name="user_id" class="kvm-select" required>
                <option value="">-- Select Existing User --</option>
                <?php foreach($users as $u) echo '<option value="' . $u->id . '">' . esc_html($u->name) . ' (' . esc_html($u->email) . ')</option>'; ?>
            </select>
        </div>
        <div>
            <label class="kvm-form-label">Websites / Quota:</label>
            <input type="number" name="custom_sites" value="10" min="1" class="kvm-input">
        </div>
        <div>
            <label class="kvm-form-label">Key Architecture:</label>
            <select name="license_mode" class="kvm-select">
                <option value="multi">One Key (Multi-site)</option>
                <option value="individual">Separate Key Per Site</option>
            </select>
        </div>
        <div>
            <label class="kvm-form-label">Expiry Duration:</label>
            <select name="expiry_choice" class="kvm-select">
                <option value="standard">1 Year (Annual)</option>
                <option value="lifetime">Lifetime</option>
            </select>
        </div>
        <div>
            <button type="submit" name="kvm_generate_key" class="kvm-btn-submit full-width">Issue License Key</button>
        </div>
    </form>
</div>

<div class="kvm-card-panel">
    <form method="post" id="kvm-bulk-licenses-form">
        <?php wp_nonce_field('kvm_bulk_license_action', 'kvm_bulk_license_nonce'); ?>
        <div class="kvm-table-header-wrap">
            <div class="kvm-bulk-actions-wrap">
                <h3 class="kvm-inline-title">Active License Database</h3>
                <select name="bulk_action" class="kvm-select bulk-select">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Set Active</option>
                    <option value="revoke">Revoke Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="submit" name="kvm_bulk_license_action" class="kvm-btn-submit compact">Apply</button>
            </div>
            
            <div class="kvm-search-wrap">
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search key, email, name..." class="kvm-input search-input">
                <a href="<?php echo admin_url('admin.php?page=keyvault-manager'); ?>" class="button kvm-reset-btn">Reset</a>
            </div>
        </div>

        <table class="kvm-table">
            <thead>
                <tr>
                    <th class="col-checkbox"><input type="checkbox" id="kvm-select-all-keys"></th>
                    <th class="col-key">License Key</th>
                    <th>Plan &amp; Mode</th>
                    <th>Customer</th>
                    <th>Quota</th>
                    <th>Expiration</th>
                    <th>Status</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($licenses)) : ?>
                    <tr><td colspan="8" class="kvm-empty-row">No licenses found.</td></tr>
                <?php else : ?>
                    <?php foreach ($licenses as $l) :
                        if ($l->status === 'parent_holder') continue;
                        $acts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_activations} WHERE license_id = %d", $l->id));

                        $quota_val = intval($l->max_activations);
                        if (!empty($l->plan_tier)) {
                            $plan_tier = sanitize_text_field($l->plan_tier);
                        } elseif ($quota_val > $pro_max) {
                            $plan_tier = 'Enterprise';
                        } elseif ($quota_val > $starter_max) {
                            $plan_tier = 'Professional';
                        } else {
                            $plan_tier = 'Starter';
                        }

                        $license_mode = isset($l->license_mode) ? $l->license_mode : 'multi';
                        $badge_class = sanitize_html_class(strtolower($plan_tier));
                        
                        $now = current_time('timestamp');
                        $grace_days = intval(get_option('kvm_grace_period_days', '7'));
                        $is_expired = (!empty($l->expires_at) && strtotime($l->expires_at) < $now);
                        $is_grace = false;
                        if ($is_expired) {
                            $grace_cutoff = strtotime($l->expires_at) + ($grace_days * 86400);
                            if ($now <= $grace_cutoff) {
                                $is_grace = true;
                            }
                        }
                        
                        $exp_display = empty($l->expires_at) ? 'Lifetime' : esc_html(date('M d, Y', strtotime($l->expires_at)));
                        ?>
                        <tr>
                            <td><input type="checkbox" name="license_ids[]" value="<?php echo $l->id; ?>" class="kvm-key-cb"></td>
                            <td><code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></td>
                            <td>
                                <strong class="kvm-plan-badge <?php echo $badge_class; ?>"><?php echo strtoupper(esc_html($plan_tier)); ?></strong>
                                <span class="kvm-mode-sub">(<?php echo strtoupper(esc_html($license_mode)); ?>)</span>
                            </td>
                            <td><strong><?php echo esc_html($l->customer_name); ?></strong><br><small class="kvm-sub-text"><?php echo esc_html($l->customer_email); ?></small></td>
                            <td>
                                <strong class="kvm-quota-text"><?php echo count($acts); ?> / <?php echo esc_html($l->max_activations); ?></strong>
                            </td>
                            <td>
                                <?php if (empty($l->expires_at)) : ?>
                                    <span class="kvm-pill lifetime">Lifetime</span>
                                <?php elseif ($is_grace) : ?>
                                    <span class="kvm-pill grace">Grace Period (<?php echo $exp_display; ?>)</span>
                                <?php elseif ($is_expired) : ?>
                                    <span class="kvm-pill expired">Expired (<?php echo $exp_display; ?>)</span>
                                <?php else : ?>
                                    <span class="kvm-date-text"><?php echo $exp_display; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="kvm-status-text <?php echo ($l->status === 'active' && (!$is_expired || $is_grace) ? ($is_grace ? 'grace' : 'active') : 'revoked'); ?>">
                                    <?php echo $is_grace ? 'GRACE PERIOD' : strtoupper(esc_html($l->status)); ?>
                                </strong>
                            </td>
                            <td>
                                <button type="button" class="button button-small kvm-btn-action" data-modal="kvm_view_details_<?php echo $l->id; ?>">
                                    👁️ View Details
                                </button>

                                <div id="kvm_view_details_<?php echo $l->id; ?>" class="kvm-modal-overlay" style="display:none;">
                                    <div class="kvm-modal-box">
                                        <div class="kvm-modal-header">
                                            <h3 class="kvm-modal-title">License Management &amp; Connected Domains</h3>
                                            <button type="button" class="kvm-modal-close" data-close="kvm_view_details_<?php echo $l->id; ?>">&times;</button>
                                        </div>

                                        <div class="kvm-modal-info-card">
                                            <p class="kvm-modal-p"><strong>License Key:</strong> <code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></p>
                                            <p class="kvm-modal-p"><strong>Customer:</strong> <?php echo esc_html($l->customer_name); ?> (<?php echo esc_html($l->customer_email); ?>)</p>
                                            <p class="kvm-modal-p"><strong>Plan Tier:</strong> <?php echo esc_html($plan_tier); ?> (<?php echo esc_html($license_mode); ?> mode)</p>
                                            <p class="kvm-modal-p"><strong>Quota:</strong> <?php echo count($acts); ?> used / <?php echo esc_html($l->max_activations); ?> max slots</p>
                                            <p class="kvm-modal-p last"><strong>Expiration:</strong> <?php echo $exp_display; ?></p>
                                        </div>

                                        <div class="kvm-modal-actions-bar">
                                            <span class="kvm-action-label">Key Actions:</span>
                                            <div class="kvm-action-btns">
                                                <button type="button" class="button button-small kvm-modal-sub-trigger" data-modal="kvm_edit_exp_<?php echo $l->id; ?>">
                                                    📅 Edit Expiry
                                                </button>
                                                <?php if ($l->status === 'active') : ?>
                                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=keyvault-manager&action=revoke&id=' . $l->id), 'revoke_key_' . $l->id)); ?>" class="button button-small kvm-revoke-btn" onclick="return confirm('Revoke this key?');">Revoke Key</a>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=keyvault-manager&action=activate&id=' . $l->id), 'activate_key_' . $l->id)); ?>" class="button button-small kvm-activate-btn">Set Active</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <h4 class="kvm-domains-heading">Connected Websites / Domains (<?php echo count($acts); ?>)</h4>
                                        <div class="kvm-domains-list">
                                            <?php if (empty($acts)) : ?>
                                                <p class="kvm-no-domains">No websites currently activated with this license key.</p>
                                            <?php else : ?>
                                                <?php foreach ($acts as $a) : 
                                                    $unlink_url = wp_nonce_url(admin_url('admin.php?page=keyvault-manager&action=admin_unlink_instant&act_id=' . $a->id), 'admin_unlink_' . $a->id);
                                                ?>
                                                    <div class="kvm-domain-row">
                                                        <code class="kvm-domain-code"><?php echo esc_html($a->domain); ?></code>
                                                        <a href="<?php echo esc_url($unlink_url); ?>" class="kvm-unlink-link" onclick="return confirm('Unlink domain <?php echo esc_js($a->domain); ?>?');">&times; Unlink</a>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="kvm-modal-footer">
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=keyvault-manager&action=delete_key&id=' . $l->id), 'delete_key_' . $l->id)); ?>" class="button kvm-delete-key-btn" onclick="return confirm('Are you sure you want to delete this key permanently?');">Delete Key Permanently</a>
                                            <button type="button" class="button button-primary kvm-modal-close-btn" data-close="kvm_view_details_<?php echo $l->id; ?>">Close</button>
                                        </div>
                                    </div>
                                </div>

                                <div id="kvm_edit_exp_<?php echo $l->id; ?>" class="kvm-modal-overlay" style="display:none;">
                                    <div class="kvm-modal-box compact">
                                        <div class="kvm-modal-header">
                                            <h3 class="kvm-modal-title">Edit Expiration Date</h3>
                                            <button type="button" class="kvm-modal-close" data-close="kvm_edit_exp_<?php echo $l->id; ?>">&times;</button>
                                        </div>
                                        <p class="kvm-modal-sub">License Key: <code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></p>
                                        
                                        <form method="post" action="">
                                            <?php wp_nonce_field('kvm_update_expiry_action', 'kvm_update_expiry_nonce'); ?>
                                            <input type="hidden" name="license_id" value="<?php echo $l->id; ?>">
                                            
                                            <div class="kvm-form-group">
                                                <label class="kvm-form-label">Expiration Rule:</label>
                                                <select name="expiry_type" class="kvm-select kvm-expiry-rule-select" data-target="kvm_modal_date_wrap_<?php echo $l->id; ?>">
                                                    <option value="extend_1y">Extend by +1 Year</option>
                                                    <option value="lifetime">Set to Lifetime (No Expiry)</option>
                                                    <option value="custom">Set Specific Custom Date</option>
                                                </select>
                                            </div>

                                            <div id="kvm_modal_date_wrap_<?php echo $l->id; ?>" class="kvm-form-group" style="display:none;">
                                                <label class="kvm-form-label">Specific Expiration Date:</label>
                                                <input type="date" name="custom_expiry_date" class="kvm-input" value="<?php echo $l->expires_at ? date('Y-m-d', strtotime($l->expires_at)) : ''; ?>">
                                            </div>

                                            <div class="kvm-modal-footer right">
                                                <button type="button" class="button kvm-modal-close-btn" data-close="kvm_edit_exp_<?php echo $l->id; ?>">Cancel</button>
                                                <button type="submit" name="kvm_update_expiry" class="kvm-btn-submit compact">Save Expiry</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="kvm-pagination-wrap">
                <div class="kvm-pagination-info">
                    Showing <?php echo ($offset + 1); ?> &ndash; <?php echo min($offset + $per_page, $total_items); ?> of <?php echo $total_items; ?> license keys
                </div>
                <div class="kvm-pagination-links">
                    <?php
                    $base_url = admin_url('admin.php?page=keyvault-manager');
                    if (!empty($search)) {
                        $base_url = add_query_arg('s', urlencode($search), $base_url);
                    }

                    if ($current_page > 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="button prev">&laquo; Previous</a>';
                    }

                    for ($p = 1; $p <= $total_pages; $p++) {
                        if ($p == $current_page) {
                            echo '<span class="button disabled active">' . $p . '</span>';
                        } elseif ($p <= 3 || $p >= $total_pages - 2 || abs($p - $current_page) <= 1) {
                            echo '<a href="' . esc_url(add_query_arg('paged', $p, $base_url)) . '" class="button">' . $p . '</a>';
                        } elseif ($p == 4 || $p == $total_pages - 3) {
                            echo '<span class="dots">...</span>';
                        }
                    }

                    if ($current_page < $total_pages) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="button next">Next &raquo;</a>';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>