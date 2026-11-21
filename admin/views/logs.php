<?php
if (!defined('ABSPATH')) { exit; }

// Handle clear logs action
if (isset($_POST['kvm_clear_logs_action']) && check_admin_referer('kvm_clear_logs_nonce', 'kvm_clear_logs_field')) {
    if (class_exists('KeyVault_Manager_Logger')) {
        KeyVault_Manager_Logger::get_instance()->clear_logs();
    }
    wp_safe_redirect(admin_url('admin.php?page=keyvault-manager&tab=logs&cleared=1'));
    exit;
}

if (isset($_GET['cleared'])) {
    echo '<div class="kvm-notice danger">🗑️ All audit logs have been successfully cleared!</div>';
}

$logs = KeyVault_Manager_Logger::get_instance()->get_logs(50);
?>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <h3 class="kvm-inline-title">API & License Audit Trail</h3>
        <form method="post" onsubmit="return confirm('Are you sure you want to clear all audit logs?');">
            <?php wp_nonce_field('kvm_clear_logs_nonce', 'kvm_clear_logs_field'); ?>
            <button type="submit" name="kvm_clear_logs_action" class="button kvm-clear-logs-btn">🗑️ Clear All Logs</button>
        </form>
    </div>

    <table class="kvm-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Endpoint</th>
                <th>Key</th>
                <th>Domain</th>
                <th>HTTP Code</th>
                <th>Log Message</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr><td colspan="6" class="kvm-empty-row">No API logs available.</td></tr>
            <?php else : ?>
                <?php foreach($logs as $l) : ?>
                    <tr>
                        <td><?php echo esc_html($l->created_at); ?></td>
                        <td><code class="kvm-code"><?php echo esc_html($l->endpoint); ?></code></td>
                        <td><code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></td>
                        <td><code class="kvm-code"><?php echo esc_html($l->domain); ?></code></td>
                        <td><span class="kvm-http-code <?php echo intval($l->status_code) === 200 ? 'success' : 'error'; ?>"><?php echo intval($l->status_code); ?></span></td>
                        <td><?php echo esc_html($l->message); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>