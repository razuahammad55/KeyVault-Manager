<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page     = 25;
$offset       = ($current_page - 1) * $per_page;

$table_licenses    = KeyVault_Manager_DB::table('licenses');
$table_users       = KeyVault_Manager_DB::table('users');
$table_activations = KeyVault_Manager_DB::table('activations');

$starter_max = intval(get_option('kvm_starter_max', 9));
$pro_max     = intval(get_option('kvm_pro_max', 99));
$grace_days  = intval(get_option('kvm_grace_period_days', 7));
$now         = strtotime(current_time('mysql'));

// One grouped query for activation counts (no per-row lookups).
$join_acts = "LEFT JOIN $table_activations a ON a.license_id = l.id";
$search_clause = '';
$search_args = array();
if (!empty($search)) {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $search_clause = 'AND (l.license_key LIKE %s OR l.customer_email LIKE %s OR l.customer_name LIKE %s)';
    $search_args = array($like, $like, $like);
}

$count_args = $search_args;
$total_items = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT l.id) FROM $table_licenses l WHERE l.status != 'parent_holder' $search_clause",
    $count_args
)));

$query_args = array_merge($search_args, array($per_page, $offset));
$licenses = $wpdb->get_results($wpdb->prepare(
    "SELECT l.*, COUNT(a.id) AS activation_count
     FROM $table_licenses l
     $join_acts
     WHERE l.status != 'parent_holder' $search_clause
     GROUP BY l.id
     ORDER BY l.id DESC
     LIMIT %d OFFSET %d",
    $query_args
));
$total_pages = max(1, (int) ceil($total_items / $per_page));

// Batch-fetch all activations for this page's licenses (single query).
$page_domains = array();
if (!empty($licenses)) {
    $page_ids = array();
    foreach ($licenses as $lic_row) {
        $page_ids[] = intval($lic_row->id);
    }
    $ids_in = implode(',', $page_ids);
    $all_acts = $wpdb->get_results("SELECT id, license_id, domain FROM $table_activations WHERE license_id IN ($ids_in) ORDER BY id ASC");
    foreach ($all_acts as $act_row) {
        $page_domains[intval($act_row->license_id)][] = $act_row;
    }
}

$users = $wpdb->get_results("SELECT id, name, email FROM $table_users ORDER BY name ASC LIMIT 500");

$total_users_count   = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_users"));
$total_keys_count    = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status != 'parent_holder'"));
$active_keys_count   = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'active'"));
$revoked_keys_count  = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'revoked'"));
$total_linked_sites  = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_activations"));

if (isset($_GET['issue_error'])) {
    echo '<div class="kvm-notice danger">❌ Select a user account before issuing keys.</div>';
}
if (isset($_GET['expiry_error'])) {
    echo '<div class="kvm-notice danger">❌ A specific date is required when choosing a custom expiration.</div>';
}
if (isset($_GET['expiry_updated'])) {
    echo '<div class="kvm-notice success">✅ Expiration updated.</div>';
}
if (isset($_GET['row_updated'])) {
    echo '<div class="kvm-notice success">✅ License updated.</div>';
}
if (isset($_GET['deleted'])) {
    echo '<div class="kvm-notice success">🗑️ License deleted.</div>';
}
if (isset($_GET['bulk_processed'])) {
    echo '<div class="kvm-notice success">✅ Bulk action completed.</div>';
}
?>

<div class="kvm-stats-grid">
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Registered Users</span>
        <h3 class="kvm-stat-val"><?php echo intval($total_users_count); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Total License Keys</span>
        <h3 class="kvm-stat-val primary"><?php echo intval($total_keys_count); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Active Keys</span>
        <h3 class="kvm-stat-val success"><?php echo intval($active_keys_count); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Revoked Keys</span>
        <h3 class="kvm-stat-val danger"><?php echo intval($revoked_keys_count); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Linked Websites</span>
        <h3 class="kvm-stat-val purple"><?php echo intval($total_linked_sites); ?></h3>
    </div>
</div>

<div class="kvm-card-panel">
    <h3>Issue New SaaS License Key</h3>
    <form method="post" class="kvm-issue-form">
        <?php wp_nonce_field('kvm_gen_action', 'kvm_gen_nonce'); ?>
        <div>
            <label class="kvm-form-label">Select User Account:</label>
            <select name="user_id" class="kvm-select" required>
                <option value="">— Select Existing User —</option>
                <?php foreach ($users as $u) : ?>
                    <option value="<?php echo intval($u->id); ?>"><?php echo esc_html($u->name . ' (' . $u->email . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="kvm-form-label">Website Slots / Keys:</label>
            <input type="number" name="custom_sites" value="10" min="1" max="10000" class="kvm-input">
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
                <option value="6_months">6 Months</option>
                <option value="3_years">3 Years</option>
                <option value="lifetime">Lifetime</option>
            </select>
        </div>
        <div class="kvm-issue-form-notes">
            <label class="kvm-form-label">Internal Notes (optional):</label>
            <input type="text" name="kvm_notes" class="kvm-input" placeholder="e.g. Agency bundle for ACME">
        </div>
        <div>
            <button type="submit" name="kvm_generate_key" class="kvm-btn kvm-btn--primary kvm-btn--block">Issue License Key</button>
        </div>
    </form>
</div>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <h3 class="kvm-inline-title">Active License Database</h3>
        <form method="get" class="kvm-search-form">
            <input type="hidden" name="page" value="keyvault-manager">
            <input type="hidden" name="tab" value="licenses">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search key, email, name…" class="kvm-input search-input">
            <button type="submit" class="kvm-btn compact">Search</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=licenses')); ?>" class="kvm-btn compact ghost">Reset</a>
        </form>
    </div>

    <form method="post" id="kvm-bulk-licenses-form">
        <?php wp_nonce_field('kvm_bulk_license_action', 'kvm_bulk_license_nonce'); ?>
        <div class="kvm-bulk-bar">
            <select name="bulk_action" class="kvm-select bulk-select">
                <option value="">Bulk Actions</option>
                <option value="activate">Set Active</option>
                <option value="revoke">Revoke Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button type="submit" name="kvm_bulk_license_action" class="kvm-btn compact" data-kvm-confirm="Apply the selected bulk action to all checked licenses?">Apply</button>
        </div>

<div class="kvm-table-scroll">
        <table class="kvm-table">
            <thead>
                <tr>
                    <th class="col-checkbox"><input type="checkbox" id="kvm-select-all-keys" aria-label="Select all licenses"></th>
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
                    <tr>
                        <td colspan="8" class="kvm-empty-cell">
                            <div class="kvm-empty">
                                <div class="kvm-empty-icon">🔑</div>
                                <p><?php echo !empty($search) ? 'No licenses match your search.' : 'No licenses issued yet. Create a user, then issue the first key above.'; ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($licenses as $l) :
                        $quota_val   = intval($l->max_activations);
                        $act_count   = intval($l->activation_count);
                        if (!empty($l->plan_tier)) {
                            $plan_tier = sanitize_text_field($l->plan_tier);
                        } elseif ($quota_val > $pro_max) {
                            $plan_tier = 'Enterprise';
                        } elseif ($quota_val > $starter_max) {
                            $plan_tier = 'Professional';
                        } else {
                            $plan_tier = 'Starter';
                        }
                        $license_mode = isset($l->license_mode) ? sanitize_text_field($l->license_mode) : 'multi';

                        $is_expired = (!empty($l->expires_at) && strtotime($l->expires_at) < $now);
                        $is_grace   = false;
                        if ($is_expired) {
                            $grace_cutoff = strtotime($l->expires_at) + ($grace_days * DAY_IN_SECONDS);
                            $is_grace = ($now <= $grace_cutoff);
                        }

                        $exp_display = empty($l->expires_at) ? 'Lifetime' : date('M d, Y', strtotime($l->expires_at));
                        $status_label = $is_grace ? 'GRACE PERIOD' : strtoupper(sanitize_text_field($l->status));

                        // Domain rows for the shared details modal (JS-populated, batch-fetched below).
                        $domains_payload = array();
                        if (isset($page_domains[intval($l->id)])) {
                            foreach ($page_domains[intval($l->id)] as $a) {
                                $domains_payload[] = array(
                                    'domain' => $a->domain,
                                    'unlink' => wp_nonce_url(admin_url('admin.php?page=keyvault-manager&action=admin_unlink_instant&act_id=' . $a->id), 'admin_unlink_' . $a->id),
                                );
                            }
                        }

                        $revoke_url   = wp_nonce_url(admin_url('admin.php?page=keyvault-manager&tab=licenses&action=revoke&id=' . $l->id), 'revoke_key_' . $l->id);
                        $activate_url = wp_nonce_url(admin_url('admin.php?page=keyvault-manager&tab=licenses&action=activate&id=' . $l->id), 'activate_key_' . $l->id);
                        $delete_url   = wp_nonce_url(admin_url('admin.php?page=keyvault-manager&tab=licenses&action=delete_key&id=' . $l->id), 'delete_key_' . $l->id);
                        ?>
                        <tr data-kvm-id="<?php echo intval($l->id); ?>"
                            data-kvm-key="<?php echo esc_attr($l->license_key); ?>"
                            data-kvm-customer="<?php echo esc_attr(trim($l->customer_name . ' — ' . $l->customer_email, ' —')); ?>"
                            data-kvm-plan="<?php echo esc_attr($plan_tier . ' · ' . strtoupper($license_mode)); ?>"
                            data-kvm-quota="<?php echo esc_attr($act_count . ' used / ' . $quota_val . ' max'); ?>"
                            data-kvm-expiry="<?php echo esc_attr($exp_display); ?>"
                            data-kvm-status="<?php echo esc_attr($status_label); ?>"
                            data-kvm-domains="<?php echo esc_attr(wp_json_encode($domains_payload)); ?>"
                            data-kvm-revoke-url="<?php echo esc_url($revoke_url); ?>"
                            data-kvm-activate-url="<?php echo esc_url($activate_url); ?>"
                            data-kvm-delete-url="<?php echo esc_url($delete_url); ?>"
                            data-kvm-expiry-current="<?php echo esc_attr($l->expires_at ? date('Y-m-d', strtotime($l->expires_at)) : ''); ?>">
                            <td class="col-checkbox"><input type="checkbox" name="license_ids[]" value="<?php echo intval($l->id); ?>" class="kvm-key-cb"></td>
                            <td><code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></td>
                            <td>
                                <strong class="kvm-plan-badge <?php echo esc_attr(strtolower($plan_tier)); ?>"><?php echo esc_html(strtoupper($plan_tier)); ?></strong>
                                <span class="kvm-mode-sub">(<?php echo esc_html(strtoupper($license_mode)); ?>)</span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($l->customer_name); ?></strong><br>
                                <small class="kvm-sub-text"><?php echo esc_html($l->customer_email); ?></small>
                            </td>
                            <td><strong class="kvm-quota-text"><?php echo intval($act_count); ?> / <?php echo intval($quota_val); ?></strong></td>
                            <td>
                                <?php if (empty($l->expires_at)) : ?>
                                    <span class="kvm-pill lifetime">Lifetime</span>
                                <?php elseif ($is_grace) : ?>
                                    <span class="kvm-pill grace">Grace (<?php echo esc_html($exp_display); ?>)</span>
                                <?php elseif ($is_expired) : ?>
                                    <span class="kvm-pill expired">Expired (<?php echo esc_html($exp_display); ?>)</span>
                                <?php else : ?>
                                    <span class="kvm-date-text"><?php echo esc_html($exp_display); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="kvm-status-text <?php echo esc_attr(($l->status === 'active' && (!$is_expired || $is_grace)) ? ($is_grace ? 'grace' : 'active') : 'revoked'); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </strong>
                            </td>
                            <td class="col-actions">
                                <button type="button" class="button button-small kvm-btn-action" data-kvm-details>👁️ View Details</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </form>

    <?php if ($total_pages > 1) :
        $base_url = admin_url('admin.php?page=keyvault-manager&tab=licenses');
        if (!empty($search)) {
            $base_url = add_query_arg('s', urlencode($search), $base_url);
        }
        ?>
        <div class="kvm-pagination-wrap">
            <div class="kvm-pagination-info">
                Showing <?php echo intval($offset + 1); ?> &ndash; <?php echo intval(min($offset + $per_page, $total_items)); ?> of <?php echo intval($total_items); ?> license keys
            </div>
            <div class="kvm-pagination-links">
                <?php
                if ($current_page > 1) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="button prev">&laquo; Previous</a>';
                }
                for ($p = 1; $p <= $total_pages; $p++) {
                    if ($p === $current_page) {
                        echo '<span class="button disabled active">' . intval($p) . '</span>';
                    } elseif ($p <= 3 || $p >= $total_pages - 2 || abs($p - $current_page) <= 1) {
                        echo '<a href="' . esc_url(add_query_arg('paged', $p, $base_url)) . '" class="button">' . intval($p) . '</a>';
                    } elseif ($p === 4 || $p === $total_pages - 3) {
                        echo '<span class="dots">…</span>';
                    }
                }
                if ($current_page < $total_pages) {
                    echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="button next">Next &raquo;</a>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
