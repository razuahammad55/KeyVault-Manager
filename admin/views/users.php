<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$user_search = isset($_GET['us']) ? sanitize_text_field($_GET['us']) : '';
$slug = get_option('kvm_plugin_slug', KVM_SLUG);
$table_users = $wpdb->prefix . $slug . '_users';
$table_licenses = $wpdb->prefix . $slug . '_licenses';

if (!empty($user_search)) {
    $users = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_users WHERE name LIKE %s OR email LIKE %s ORDER BY id DESC", '%' . $wpdb->esc_like($user_search) . '%', '%' . $wpdb->esc_like($user_search) . '%'));
} else {
    $users = $wpdb->get_results("SELECT * FROM $table_users ORDER BY id DESC");
}

if (isset($_GET['user_created'])) echo '<div class="kvm-notice success">✅ New User Created Successfully!</div>';
if (isset($_GET['user_updated'])) echo '<div class="kvm-notice success">✅ User Information & Password Updated!</div>';
if (isset($_GET['deleted'])) echo '<div class="kvm-notice danger">🗑️ User deletion action completed successfully!</div>';
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
            <label class="kvm-form-label">Custom Password (Optional):</label>
            <input type="text" name="user_password" class="kvm-input" placeholder="Auto-generated strong temp pass if empty">
        </div>
        <div>
            <button type="submit" name="kvm_create_user" class="kvm-btn-submit full-width">Create User Account</button>
        </div>
    </form>
</div>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <h3 class="kvm-inline-title">Registered User Database</h3>
        <form method="get" class="kvm-search-form">
            <input type="hidden" name="page" value="keyvault-manager">
            <input type="hidden" name="tab" value="users">
            <input type="text" name="us" value="<?php echo esc_attr($user_search); ?>" placeholder="Search user name or email..." class="kvm-input search-input">
            <button type="submit" class="kvm-btn-submit compact">Search Users</button>
        </form>
    </div>

    <table class="kvm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User Name</th>
                <th>Email Address</th>
                <th>Password (Admin View)</th>
                <th>Issued Keys</th>
                <th>Registered Date</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)) : ?>
                <tr><td colspan="7" class="kvm-empty-row">No registered users found.</td></tr>
            <?php else : ?>
                <?php foreach ($users as $u) : 
                    $keys_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_licenses WHERE (user_id = %d OR customer_email = %s) AND status != 'parent_holder'", $u->id, $u->email)));
                    $pwd_display = !empty($u->temp_password) ? $u->temp_password : 'N/A';
                    ?>
                    <tr>
                        <td>#<?php echo $u->id; ?></td>
                        <td><strong><?php echo esc_html($u->name); ?></strong></td>
                        <td><code><?php echo esc_html($u->email); ?></code></td>
                        <td><code class="kvm-pwd-code"><?php echo esc_html($pwd_display); ?></code></td>
                        <td><strong class="kvm-quota-text"><?php echo $keys_count; ?> Keys</strong></td>
                        <td><?php echo esc_html($u->created_at); ?></td>
                        <td>
                            <div class="kvm-row-actions">
                                <button type="button" class="kvm-btn-edit-user" data-id="<?php echo $u->id; ?>" data-name="<?php echo esc_attr($u->name); ?>" data-email="<?php echo esc_attr($u->email); ?>" data-pass="<?php echo esc_attr($u->temp_password); ?>">Edit User</button>
                                <button type="button" class="kvm-btn-delete-user" data-id="<?php echo $u->id; ?>" data-name="<?php echo esc_attr($u->name); ?>">Delete User</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>