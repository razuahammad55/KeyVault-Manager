<?php
if (!defined('ABSPATH')) { exit; }
global $wpdb;

$table_orders = KeyVault_Manager_DB::table('orders');

$search_order  = isset($_GET['os']) ? sanitize_text_field(wp_unslash($_GET['os'])) : '';
$current_page  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page      = 20;
$offset        = ($current_page - 1) * $per_page;

$search_clause = '';
$search_args   = array();
if (!empty($search_order)) {
    $like = '%' . $wpdb->esc_like($search_order) . '%';
    $search_clause = 'WHERE order_id LIKE %s OR customer_email LIKE %s OR customer_name LIKE %s';
    $search_args   = array($like, $like, $like);
}

// Aggregates computed in SQL, not PHP.
$total_orders = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_orders $search_clause", $search_args)));
$total_revenue = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM $table_orders $search_clause", $search_args)));
$stripe_revenue = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM $table_orders WHERE gateway LIKE %s $search_clause", array_merge(array('%stripe%'), $search_args))));
$paypal_revenue = max(0.0, $total_revenue - $stripe_revenue);

$total_pages = max(1, (int) ceil($total_orders / $per_page));

$query_args = array_merge($search_args, array($per_page, $offset));
$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_orders $search_clause ORDER BY id DESC LIMIT %d OFFSET %d",
    $query_args
));
?>

<div class="kvm-orders-summary">
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Total Orders</span>
        <h3 class="kvm-stat-val success"><?php echo intval($total_orders); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Total Revenue</span>
        <h3 class="kvm-stat-val">$<?php echo esc_html(number_format($total_revenue, 2)); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">Stripe Revenue</span>
        <h3 class="kvm-stat-val primary">$<?php echo esc_html(number_format($stripe_revenue, 2)); ?></h3>
    </div>
    <div class="kvm-stat-card">
        <span class="kvm-stat-label">PayPal Revenue</span>
        <h3 class="kvm-stat-val purple">$<?php echo esc_html(number_format($paypal_revenue, 2)); ?></h3>
    </div>
</div>

<div class="kvm-card-panel">
    <div class="kvm-table-header-wrap">
        <div>
            <h3 class="kvm-section-heading">🛒 Store Orders &amp; Transactions</h3>
            <p class="kvm-section-sub">Completed customer transactions, gateways, slot quotas and issued license keys.</p>
        </div>
        <form method="get" class="kvm-search-form">
            <input type="hidden" name="page" value="keyvault-manager">
            <input type="hidden" name="tab" value="orders">
            <input type="text" name="os" value="<?php echo esc_attr($search_order); ?>" placeholder="Search order, email, name…" class="kvm-input search-input">
            <button type="submit" class="kvm-btn compact">Search</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=keyvault-manager&tab=orders')); ?>" class="kvm-btn compact ghost">Reset</a>
        </form>
    </div>

    <div class="kvm-table-scroll">
    <table class="kvm-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Gateway</th>
                <th>Date</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)) : ?>
                <tr>
                    <td colspan="7" class="kvm-empty-cell">
                        <div class="kvm-empty">
                            <div class="kvm-empty-icon">🛒</div>
                            <p>No orders recorded yet. Completed Stripe/PayPal checkouts appear here automatically.</p>
                        </div>
                    </td>
                </tr>
            <?php else : foreach ($orders as $order) : ?>
                <tr data-kvm-order="<?php echo esc_attr(wp_json_encode(array(
                    'order_id'       => $order->order_id,
                    'customer_name'  => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'amount'         => floatval($order->amount),
                    'gateway'        => $order->gateway,
                    'quantity'       => intval($order->quantity),
                    'license_mode'   => $order->license_mode,
                    'license_keys'   => $order->license_keys,
                ))); ?>">
                    <td><strong><?php echo esc_html($order->order_id); ?></strong></td>
                    <td><?php echo esc_html($order->customer_name); ?></td>
                    <td><?php echo esc_html($order->customer_email); ?></td>
                    <td><strong>$<?php echo esc_html(number_format(floatval($order->amount), 2)); ?></strong></td>
                    <td><span class="kvm-pill lifetime"><?php echo esc_html($order->gateway); ?></span></td>
                    <td><?php echo esc_html($order->created_at); ?></td>
                    <td class="col-actions">
                        <button type="button" class="button button-small" data-kvm-order-open>View Details</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1) :
        $base_url = admin_url('admin.php?page=keyvault-manager&tab=orders');
        if (!empty($search_order)) {
            $base_url = add_query_arg('os', urlencode($search_order), $base_url);
        }
        ?>
        <div class="kvm-pagination-wrap">
            <div class="kvm-pagination-info">
                Showing <?php echo intval($offset + 1); ?> &ndash; <?php echo intval(min($offset + $per_page, $total_orders)); ?> of <?php echo intval($total_orders); ?> orders
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
