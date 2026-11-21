<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$user_search = isset($_GET['us']) ? sanitize_text_field(wp_unslash($_GET['us'])) : '';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page     = 20;
$offset       = ($current_page - 1) * $per_page;

$table_users    = KeyVault_Manager_DB::table('users');
$table_licenses = KeyVault_Manager_DB::table('licenses');

$search_clause = '';
$search_args   = array();
if (!empty($user_search)) {
    $like = '%' . $wpdb->esc_like($user_search) . '%';
    $search_clause = 'WHERE u.name LIKE %s OR u.email LIKE %s';
    $search_args   = array($like, $like);
}

$total_items = intval($wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table_users u $search_clause",
    $search_args
)));
$total_pages = max(1, (int) ceil($total_items / $per_page));

$query_args = array_merge($search_args, array($per_page, $offset));
// Key counts computed in SQL (no per-row queries); plaintext passwords are never exposed.
$users = $wpdb->get_results($wpdb->prepare(
    "SELECT u.*, (
        SELECT COUNT(*) FROM $table_licenses
        WHERE (user_id = u.id OR customer_email = u.email) AND status != 'parent_holder'
     ) AS keys_count
     FROM $table_users u $search_clause
     ORDER BY u.id DESC LIMIT %d OFFSET %d",
    $query_args
));

if (isset($_GET['user_created'])) echo '<div class="kvm-notice success">✅ New user created — the temporary password is shown in the notice above.</div>';
if (isset($_GET['user_updated'])) echo '<div class="kvm-notice success">✅ User information updated.</div>';
if (isset($_GET['deleted'])) echo '<div class="kvm-notice danger">🗑️ User deletion action completed.</div>';
?>

<div class="kvm-card-panel">
    <h3>Add New User Account</h3>
    <form method="post" class="kvm-user-form">
        <?php wp_nonce_field('kvm_create_user_action', 'kvm_create_user_nonce'); ?>
        <div>
            <label class="kvm-form-label">Full Name:</label>
            <input type="text" name="user_name" class="kvm-input" placeholder="Razu Ahammad" required>
        </div>
        <div>
            <label class="kvm-form-label">Email Address:</label>
            <input type="email" name="user_email" class="kvm-input" placeholder="razu@example.com" required>
        </div>
        <div>
            <label class="kvm-form-label">Custom Password (optional):</label>
            <input type="text" name="user_password" class="kvm-input" placeholder="Auto-generated strong temp pass if empty" autocomplete="new-password">
        </div>
        <div>
            <button type="submit" name="kvm_create_user" class="kvm-btn kvm-btn--primary kvm-btn--block">Create User Account</button>
        </div>
    </form>
</div>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <h3 class="kvm-inline-title">Registered User Database</h3>
        <form method="get" class="kvm-search-form">
            <input type="hidden" name="page" value="keyvault-manager">
            <input type="hidden" name="tab" value="users">
            <input type="text" name="us" value="<?php echo esc_attr($user_search); ?>" placeholder="Search user name or email…" class="kvm-input search-input">
            <button type="submit" class="kvm-btn compact">Search Users</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=users')); ?>" class="kvm-btn compact ghost">Reset</a>
        </form>
    </div>

    <div class="kvm-table-scroll">
    <table class="kvm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User Name</th>
                <th>Email Address</th>
                <th>Issued Keys</th>
                <th>Registered</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)) : ?>
                <tr>
                    <td colspan="6" class="kvm-empty-cell">
                        <div class="kvm-empty">
                            <div class="kvm-empty-icon">👤</div>
                            <p>No registered users found. Portal accounts are created automatically at checkout, or add one above.</p>
                        </div>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($users as $u) : ?>
                    <tr>
                        <td>#<?php echo intval($u->id); ?></td>
                        <td><strong><?php echo esc_html($u->name); ?></strong></td>
                        <td><code class="kvm-sub-text"><?php echo esc_html($u->email); ?></code></td>
                        <td><strong class="kvm-quota-text"><?php echo intval($u->keys_count); ?> Keys</strong></td>
                        <td><?php echo esc_html($u->created_at); ?></td>
                        <td>
                            <div class="kvm-row-actions">
                                <button type="button" class="kvm-btn-edit-user"
                                    data-id="<?php echo intval($u->id); ?>"
                                    data-name="<?php echo esc_attr($u->name); ?>"
                                    data-email="<?php echo esc_attr($u->email); ?>">Edit</button>
                                <button type="button" class="kvm-btn-delete-user"
                                    data-id="<?php echo intval($u->id); ?>"
                                    data-name="<?php echo esc_attr($u->name); ?>">Delete</button>
                                <a class="kvm-btn-pass-mail" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=keyvault-manager&tab=users&kvm_user_action=new_password&user=' . intval($u->id)), 'kvm_new_password_' . intval($u->id))); ?>"
                                    onclick="return confirm('Generate and email a new temporary password to <?php echo esc_js($u->email); ?>?');">Email New Password</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1) :
        $base_url = admin_url('admin.php?page=keyvault-manager&tab=users');
        if (!empty($user_search)) {
            $base_url = add_query_arg('us', urlencode($user_search), $base_url);
        }
        ?>
        <div class="kvm-pagination-wrap">
            <div class="kvm-pagination-info">
                Showing <?php echo intval($offset + 1); ?> &ndash; <?php echo intval(min($offset + $per_page, $total_items)); ?> of <?php echo intval($total_items); ?> users
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
