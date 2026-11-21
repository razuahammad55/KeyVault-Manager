<?php
if (!defined('ABSPATH')) { exit; }

global $wpdb;
$slug = KVM_SLUG;
$table_orders = $wpdb->prefix . $slug . '_orders';

// Fetch all recorded orders
$orders = $wpdb->get_results("SELECT * FROM {$table_orders} ORDER BY id DESC");

// Calculate Metrics & Revenue Breakdown
$total_orders = count($orders);
$total_revenue = 0;
$stripe_revenue = 0;
$paypal_revenue = 0;

foreach ($orders as $o) {
    $amt = floatval($o->amount);
    $total_revenue += $amt;
    if (stripos($o->gateway, 'stripe') !== false) {
        $stripe_revenue += $amt;
    } else {
        $paypal_revenue += $amt;
    }
}
?>


<!-- Compact Orders Summary Header -->
<div class="kvm-card-panel top-margin" style="margin-bottom: 20px; padding: 15px 20px;">
    <div style="display: grid; grid-template-columns: 1fr 1.5fr 2fr; gap: 15px; align-items: center;">
        
        <!-- Total Orders Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 15px; text-align: center;">
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Orders</span>
            <div style="font-size: 24px; font-weight: 800; color: #10b981; margin-top: 2px;"><?php echo esc_html($total_orders); ?></div>
        </div>

        <!-- Total Revenue Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 15px; text-align: center;">
            <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Revenue</span>
            <div style="font-size: 24px; font-weight: 900; color: #0f172a; margin-top: 2px;">$<?php echo number_format($total_revenue, 2); ?></div>
        </div>

        <!-- Gateway Breakdown Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 15px; display: flex; justify-content: space-around; align-items: center;">
            <div style="text-align: center;">
                <span style="font-size: 11px; font-weight: 700; color: #64748b; display: block; text-transform: uppercase;">Stripe</span>
                <strong style="font-size: 16px; color: #2563eb;">$<?php echo number_format($stripe_revenue, 2); ?></strong>
            </div>
            <div style="border-right: 1px solid #cbd5e1; height: 30px;"></div>
            <div style="text-align: center;">
                <span style="font-size: 11px; font-weight: 700; color: #64748b; display: block; text-transform: uppercase;">PayPal</span>
                <strong style="font-size: 16px; color: #2563eb;">$<?php echo number_format($paypal_revenue, 2); ?></strong>
            </div>
        </div>

    </div>
</div>

<!-- Orders List Table -->
<div class="kvm-card-panel">
    <h3 class="kvm-section-heading">🛒 Store Orders & Transactions</h3>
    <p class="kvm-section-sub">Review completed customer transactions, payment gateways, slot quotas, and issued license keys.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Gateway</th>
                <th>Date</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 25px; color: #64748b;">No orders recorded yet.</td></tr>
            <?php else: foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?php echo esc_html($order->order_id); ?></strong></td>
                    <td><?php echo esc_html($order->customer_name); ?></td>
                    <td><?php echo esc_html($order->customer_email); ?></td>
                    <td><strong>$<?php echo number_format(floatval($order->amount), 2); ?></strong></td>
                    <td>
                        <span style="background: #eff6ff; color: #2563eb; padding: 3px 10px; border-radius: 6px; font-weight: 700; font-size: 11px;">
                            <?php echo esc_html($order->gateway); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($order->created_at); ?></td>
                    <td style="text-align: right;">
                        <button type="button" class="button button-small" onclick="kvmOpenOrderModal(<?php echo esc_attr(json_encode($order)); ?>)">View Details</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Order Details Modal -->
<div id="kvm-order-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999999; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; width:100%; max-width:600px; padding:35px; color:#0f172a; box-shadow:0 25px 50px rgba(0,0,0,0.25); position:relative;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #f1f5f9; padding-bottom:15px; margin-bottom:20px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Order Details: <span id="modal-order-id" style="color:#2563eb;"></span></h3>
            <button type="button" onclick="document.getElementById('kvm-order-modal').style.display='none'" style="background:none; border:none; color:#64748b; font-size:24px; cursor:pointer; font-weight:bold;">&times;</button>
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div><strong>Customer Name:</strong> <p id="modal-name" style="margin:4px 0 0 0; color:#475569;"></p></div>
            <div><strong>Email Address:</strong> <p id="modal-email" style="margin:4px 0 0 0; color:#475569;"></p></div>
            <div><strong>Total Paid:</strong> <p id="modal-amount" style="margin:4px 0 0 0; color:#10b981; font-weight:800;"></p></div>
            <div><strong>Payment Gateway:</strong> <p id="modal-gateway" style="margin:4px 0 0 0; color:#475569;"></p></div>
            <div><strong>Website Slots:</strong> <p id="modal-qty" style="margin:4px 0 0 0; color:#475569;"></p></div>
            <div><strong>License Mode:</strong> <p id="modal-mode" style="margin:4px 0 0 0; color:#475569;"></p></div>
        </div>

        <div style="margin-bottom: 25px;">
            <strong style="display:block; margin-bottom:6px; color:#0f172a;">Generated License Keys:</strong>
            <textarea id="modal-keys" readonly class="large-text code" rows="3" style="width:100%; background:#f8fafc; font-family:monospace; padding:10px; border-radius:8px; border:1px solid #e2e8f0;"></textarea>
        </div>

        <button type="button" class="button button-primary" onclick="document.getElementById('kvm-order-modal').style.display='none'" style="width:100%;">Close Window</button>
    </div>
</div>

<script>
function kvmOpenOrderModal(order) {
    document.getElementById('modal-order-id').textContent = order.order_id || 'N/A';
    document.getElementById('modal-name').textContent = order.customer_name || 'N/A';
    document.getElementById('modal-email').textContent = order.customer_email || 'N/A';
    document.getElementById('modal-amount').textContent = '$' + parseFloat(order.amount || 0).toFixed(2);
    document.getElementById('modal-gateway').textContent = order.gateway || 'N/A';
    document.getElementById('modal-qty').textContent = order.quantity || '1';
    document.getElementById('modal-mode').textContent = order.license_mode || 'multi';
    document.getElementById('modal-keys').value = order.license_keys || 'No keys recorded.';
    
    document.getElementById('kvm-order-modal').style.display = 'flex';
}
</script>