<?php
require __DIR__ . '/includes/bootstrap.php';
login_required();

// ---------------- Form actions (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(function () {
        switch (post('action')) {
            case 'add':
                $itemId = execute('INSERT INTO inventory_items(item_code,item_name,category,unit,quantity,reserved_quantity,reorder_level,unit_cost,location)
                    VALUES (?,?,?,?,?,?,?,?,?)',
                    [post('item_code'), post('item_name'), post('category'), post('unit'), post_num('quantity'),
                     post_num('reserved_quantity'), post_num('reorder_level'), post_num('unit_cost'), post('location')]);
                $initial = (int) post('quantity', '0');
                if ($initial) {
                    execute("INSERT INTO inventory_history(item_id,transaction_type,quantity,reference_no,remarks,user_id) VALUES (?,'Stock In',?,?,?,?)",
                        [$itemId, $initial, 'INITIAL', 'Initial stock', $_SESSION['user_id']]);
                }
                flash('Inventory item added.');
                break;

            case 'update':
                execute('UPDATE inventory_items SET item_name=?,category=?,unit=?,reserved_quantity=?,reorder_level=?,unit_cost=?,location=? WHERE id=?',
                    [post('item_name'), post('category'), post('unit'), post_num('reserved_quantity'), post_num('reorder_level'),
                     post_num('unit_cost'), post('location'), (int) post('item_id', '0')]);
                flash('Item updated.');
                break;

            case 'transaction':
                $itemId = (int) post('item_id', '0');
                $qty = (int) post('quantity', '0');
                $type = post('transaction_type');
                $item = query_one('SELECT * FROM inventory_items WHERE id=?', [$itemId]);
                if (!$item || $qty <= 0 || !in_array($type, ['Stock In', 'Stock Out'], true)) {
                    flash('Invalid inventory transaction.', 'danger');
                    break;
                }
                if ($type === 'Stock Out' && $item['quantity'] < $qty) {
                    flash('Not enough stock available.', 'danger');
                    break;
                }
                $delta = $type === 'Stock In' ? $qty : -$qty;
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    execute('UPDATE inventory_items SET quantity = quantity + ? WHERE id=?', [$delta, $itemId]);
                    execute('INSERT INTO inventory_history(item_id,transaction_type,quantity,reference_no,remarks,user_id) VALUES (?,?,?,?,?,?)',
                        [$itemId, $type, $qty, post('reference_no'), post('remarks'), $_SESSION['user_id']]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                log_activity($type, $item['item_code'] . ' - ' . $qty);
                flash("$type recorded.");
                break;
        }
        redirect('inventory.php');
    }, 'inventory.php', 'That item code already exists.');
}

// ---------------- Page data (GET) ----------------
$q = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'All';

$sql = 'SELECT * FROM inventory_items WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (item_code LIKE ? OR item_name LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if ($category !== 'All') {
    $sql .= ' AND category = ?';
    $params[] = $category;
}
$sql .= ' ORDER BY item_name';
$items = query_all($sql, $params);
$categories = array_column(query_all('SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL ORDER BY category'), 'category');
$summary = query_one('SELECT COUNT(*) total_items, COALESCE(SUM(quantity),0) total_quantity,
        COALESCE(SUM(reserved_quantity),0) reserved_quantity,
        COALESCE(SUM(quantity-reserved_quantity),0) available_quantity FROM inventory_items');
$history = query_all('SELECT h.*, i.item_code, i.item_name, u.full_name
    FROM inventory_history h JOIN inventory_items i ON i.id=h.item_id
    LEFT JOIN users u ON u.id=h.user_id ORDER BY h.created_at DESC LIMIT 30');

layout_start('Inventory - RPV', 'inventory');
?>
<div class="page-head"><div><p class="crumb">Home › Inventory</p><h1>Inventory</h1><p>Current stock overview and movement history</p></div></div>

<div class="cards four">
    <div class="metric"><small>Total Items</small><strong><?= h($summary['total_items']) ?></strong></div>
    <div class="metric"><small>Total Quantity</small><strong><?= h($summary['total_quantity']) ?></strong></div>
    <div class="metric"><small>Reserved Quantity</small><strong><?= h($summary['reserved_quantity']) ?></strong></div>
    <div class="metric"><small>Available Quantity</small><strong><?= h($summary['available_quantity']) ?></strong></div>
</div>

<div class="section-head">
    <h2>Inventory Table</h2>
    <div><button class="btn primary" data-modal="itemAdd">+ Add Item</button> <button class="btn" data-modal="stockTxn">↻ Stock In / Out</button></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Search item code or name">
    <select name="category"><option>All</option><?= options($categories, $category) ?></select>
    <button class="btn">Search</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Code</th><th>Item</th><th>Category</th><th>Qty</th><th>Reserved</th><th>Available</th><th>Reorder Level</th><th>Location</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><?= h($i['item_code']) ?></td>
                <td><?= h($i['item_name']) ?></td>
                <td><?= dash($i['category']) ?></td>
                <td><?= h($i['quantity']) ?></td>
                <td><?= h($i['reserved_quantity']) ?></td>
                <td><?= (int) $i['quantity'] - (int) $i['reserved_quantity'] ?></td>
                <td><?= h($i['reorder_level']) ?></td>
                <td><?= dash($i['location']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="8">No inventory items.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Inventory History</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Item</th><th>Transaction</th><th>Qty</th><th>Reference</th><th>Staff</th></tr></thead>
        <tbody>
        <?php foreach ($history as $hrow): ?>
            <tr>
                <td><?= h($hrow['created_at']) ?></td>
                <td><?= h($hrow['item_code']) ?> — <?= h($hrow['item_name']) ?></td>
                <td><?= h($hrow['transaction_type']) ?></td>
                <td><?= h($hrow['quantity']) ?></td>
                <td><?= dash($hrow['reference_no']) ?></td>
                <td><?= dash($hrow['full_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="6">No history.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php layout_end(); ?>

<div class="modal" id="itemAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Inventory Item</h2>
        <form method="post" action="inventory.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Item Code<input name="item_code" required></label>
            <label>Item Name<input name="item_name" required></label>
            <label>Category<input name="category"></label>
            <label>Unit<input name="unit"></label>
            <label>Initial Quantity<input type="number" name="quantity" value="0"></label>
            <label>Reserved<input type="number" name="reserved_quantity" value="0"></label>
            <label>Reorder Level<input type="number" name="reorder_level" value="0"></label>
            <label>Unit Cost<input type="number" step="0.01" name="unit_cost" value="0"></label>
            <label>Location<input name="location"></label>
            <button class="btn primary wide">Add Item</button>
        </form>
    </div>
</div>

<div class="modal" id="stockTxn">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Stock Transaction</h2>
        <form method="post" action="inventory.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="transaction">
            <label>Item
                <select name="item_id" required>
                    <?php foreach ($items as $i): ?>
                        <option value="<?= (int) $i['id'] ?>"><?= h($i['item_code']) ?> — <?= h($i['item_name']) ?> (<?= h($i['quantity']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Transaction<select name="transaction_type"><option>Stock In</option><option>Stock Out</option></select></label>
            <label>Quantity<input type="number" min="1" name="quantity" required></label>
            <label>Reference No.<input name="reference_no"></label>
            <label>Remarks<textarea name="remarks"></textarea></label>
            <button class="btn primary">Record</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
