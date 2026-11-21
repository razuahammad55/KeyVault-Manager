<?php
if (!defined('ABSPATH')) { exit; }

$logs        = KeyVault_Manager_Logger::get_instance()->get_logs(50);
$total_logs  = KeyVault_Manager_Logger::get_instance()->count_logs();
$retention   = intval(apply_filters('kvm_log_retention_days', KVM_LOG_RETENTION_DAYS));

if (isset($_GET['cleared'])) {
    echo '<div class="kvm-notice danger">🗑️ All audit logs have been cleared.</div>';
}
?>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <div>
            <h3 class="kvm-inline-title">API &amp; License Audit Trail</h3>
            <p class="kvm-section-sub"><?php echo intval($total_logs); ?> entries. Automatic retention keeps the last <?php echo intval($retention); ?> days (filter <code>kvm_log_retention_days</code>).</p>
        </div>
        <form method="post" onsubmit="return confirm('Are you sure you want to clear all audit logs?');">
            <?php wp_nonce_field('kvm_clear_logs_nonce', 'kvm_clear_logs_field'); ?>
            <button type="submit" name="kvm_clear_logs_action" class="kvm-clear-logs-btn">🗑️ Clear All Logs</button>
        </form>
    </div>

    <div class="kvm-table-scroll">
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
                <tr>
                    <td colspan="6" class="kvm-empty-cell">
                        <div class="kvm-empty">
                            <div class="kvm-empty-icon">📜</div>
                            <p>No API logs available yet. License activations, deactivations and blocked attempts are recorded here.</p>
                        </div>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $l) : ?>
                    <tr>
                        <td><?php echo esc_html($l->created_at); ?></td>
                        <td><code class="kvm-code"><?php echo esc_html($l->endpoint); ?></code></td>
                        <td><code class="kvm-code"><?php echo esc_html($l->license_key); ?></code></td>
                        <td><code class="kvm-sub-text"><?php echo esc_html($l->domain); ?></code></td>
                        <td><span class="kvm-http-code <?php echo intval($l->status_code) === 200 ? 'success' : 'error'; ?>"><?php echo intval($l->status_code); ?></span></td>
                        <td><?php echo esc_html($l->message); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
