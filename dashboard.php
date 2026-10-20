<?php
require __DIR__ . '/includes/bootstrap.php';
login_required();

$count = fn(string $sql): int => (int) query_one($sql)['total'];

$metrics = [
    'customers'           => $count('SELECT COUNT(*) total FROM customers'),
    'inquiries'           => $count("SELECT COUNT(*) total FROM inquiries WHERE status IN ('Open','Pending')"),
    'quotations'          => $count("SELECT COUNT(*) total FROM quotations WHERE status='Pending'"),
    'projects'            => $count("SELECT COUNT(*) total FROM projects WHERE status IN ('Ongoing','Delayed')"),
    'available_inventory' => $count('SELECT COALESCE(SUM(quantity-reserved_quantity),0) total FROM inventory_items'),
    'low_stock'           => $count('SELECT COUNT(*) total FROM inventory_items WHERE quantity-reserved_quantity <= reorder_level'),
    'active_pos'          => $count("SELECT COUNT(*) total FROM purchase_orders WHERE status IN ('Pending','Approved','In Transit','Delayed')"),
];

$recentPo = query_all('
    SELECT po.po_number, s.name AS supplier, po.total_value, po.status, po.created_at,
           COALESCE((SELECT SUM(quantity) FROM purchase_order_items x WHERE x.purchase_order_id = po.id), 0) AS item_count
    FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.created_at DESC LIMIT 5');
$activity = query_all('SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 8');
$delayed = $count("SELECT COUNT(*) total FROM purchase_orders WHERE status='Delayed'");
$pendingApprovals = $metrics['quotations'] + $count("SELECT COUNT(*) total FROM purchase_orders WHERE status='Pending'");

layout_start('Dashboard - RPV', 'dashboard');
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= h($_SESSION['full_name']) ?>!</p>
    </div>
    <div class="segmented">
        <button>7 days</button>
        <button class="selected">30 days</button>
        <button>Quarterly</button>
    </div>
</div>

<h3>Alerts</h3>
<div class="cards three">
    <div class="alert-card"><b> ⚡︎  LOW STOCK</b><span><?= $metrics['low_stock'] ?> item(s)</span></div>
    <div class="alert-card"><b>✕ DELAYED DELIVERY</b><span><?= $delayed ?> purchase order(s)</span></div>
    <div class="alert-card"><b>ⓘ PENDING APPROVALS</b><span><?= $pendingApprovals ?> record(s)</span></div>
</div>

<h3>Key Metrics</h3>
<div class="cards metrics-grid">
    <div class="metric"><small>Total Customers</small><strong><?= $metrics['customers'] ?></strong></div>
    <div class="metric"><small>Active Inquiries</small><strong><?= $metrics['inquiries'] ?></strong></div>
    <div class="metric"><small>Pending Quotations</small><strong><?= $metrics['quotations'] ?></strong></div>
    <div class="metric"><small>Ongoing Projects</small><strong><?= $metrics['projects'] ?></strong></div>
    <div class="metric"><small>Available Inventory</small><strong><?= $metrics['available_inventory'] ?></strong></div>
    <div class="metric"><small>Low Stock Items</small><strong><?= $metrics['low_stock'] ?></strong></div>
    <div class="metric"><small>Active Purchase Orders</small><strong><?= $metrics['active_pos'] ?></strong></div>
</div>

<div class="two-col">
    <section>
        <h3>Recent Activity</h3>
        <div class="panel timeline">
            <?php foreach ($activity as $a): ?>
                <div>
                    <b><?= h($a['action']) ?></b>
                    <small><?= h($a['description']) ?> · <?= h($a['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$activity): ?><p class="muted">No recent activity.</p><?php endif; ?>
        </div>
    </section>

    <section>
        <h3>Recent Purchase Orders</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>PO Number</th><th>Supplier</th><th>Items</th><th>Value</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentPo as $po): ?>
                    <tr>
                        <td><?= h($po['po_number']) ?></td>
                        <td><?= dash($po['supplier']) ?></td>
                        <td><?= h($po['item_count']) ?></td>
                        <td><?= money($po['total_value']) ?></td>
                        <td><span class="badge"><?= h($po['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentPo): ?><tr><td colspan="5">No purchase orders.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
layout_end();
page_end();
