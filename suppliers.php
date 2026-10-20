<?php
require __DIR__ . '/includes/bootstrap.php';
login_required();

$statuses = ['Active', 'Inactive', 'On hold'];

// ---------------- Form actions (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'delete') {
        admin_required();
    }
    handle_post(function () use ($action) {
        switch ($action) {
            case 'add':
                execute('INSERT INTO suppliers(supplier_code,name,category,contact_person,email,phone,address,status) VALUES (?,?,?,?,?,?,?,?)',
                    [post('supplier_code'), post('name'), post('category'), post('contact_person'), post('email'),
                     post('phone'), post('address'), post('status', 'Active')]);
                log_activity('New supplier', post('name'));
                flash('Supplier added.');
                break;
            case 'edit':
                execute('UPDATE suppliers SET name=?,category=?,contact_person=?,email=?,phone=?,address=?,status=? WHERE id=?',
                    [post('name'), post('category'), post('contact_person'), post('email'), post('phone'),
                     post('address'), post('status'), (int) post('supplier_id', '0')]);
                flash('Supplier updated.');
                break;
            case 'delete':
                execute('DELETE FROM suppliers WHERE id=?', [(int) post('supplier_id', '0')]);
                flash('Supplier deleted.');
                break;
        }
        redirect('suppliers.php');
    }, 'suppliers.php', 'That supplier code already exists.');
}

// ---------------- Page data (GET) ----------------
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'All';
$category = $_GET['category'] ?? 'All';

$sql = 'SELECT * FROM suppliers WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR contact_person LIKE ? OR supplier_code LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status !== 'All') { $sql .= ' AND status = ?'; $params[] = $status; }
if ($category !== 'All') { $sql .= ' AND category = ?'; $params[] = $category; }
$sql .= ' ORDER BY name';
$rows = query_all($sql, $params);
$cats = array_column(query_all('SELECT DISTINCT category FROM suppliers WHERE category IS NOT NULL ORDER BY category'), 'category');

$count = fn(string $s): int => (int) query_one($s)['total'];
$metrics = [
    'total'    => $count('SELECT COUNT(*) total FROM suppliers'),
    'active'   => $count("SELECT COUNT(*) total FROM suppliers WHERE status='Active'"),
    'inactive' => $count("SELECT COUNT(*) total FROM suppliers WHERE status='Inactive'"),
    'delayed'  => $count("SELECT COUNT(DISTINCT supplier_id) total FROM purchase_orders WHERE status='Delayed' AND supplier_id IS NOT NULL"),
];

layout_start('Suppliers - RPV', 'suppliers');
?>
<div class="page-head"><h1>Supplier Management</h1><button class="btn primary" data-modal="supplierAdd">+ Add Supplier</button></div>

<div class="cards four">
    <div class="metric"><small>Total Suppliers</small><strong><?= $metrics['total'] ?></strong></div>
    <div class="metric"><small>Active</small><strong><?= $metrics['active'] ?></strong></div>
    <div class="metric"><small>Inactive</small><strong><?= $metrics['inactive'] ?></strong></div>
    <div class="metric"><small>With Delayed PO</small><strong><?= $metrics['delayed'] ?></strong></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Search suppliers...">
    <select name="status"><option>All</option><?= options($statuses, $status) ?></select>
    <select name="category"><option>All</option><?= options($cats, $category) ?></select>
    <button class="btn">Filter</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Code</th><th>Supplier</th><th>Category</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
            <tr>
                <td><?= h($s['supplier_code']) ?></td>
                <td><?= h($s['name']) ?></td>
                <td><?= dash($s['category']) ?></td>
                <td><?= dash($s['contact_person']) ?></td>
                <td><?= dash($s['email']) ?></td>
                <td><?= dash($s['phone']) ?></td>
                <td><span class="badge"><?= h($s['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7">No suppliers.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php layout_end(); ?>

<div class="modal" id="supplierAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Supplier</h2>
        <form method="post" action="suppliers.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Supplier Code<input name="supplier_code" required></label>
            <label>Supplier Name<input name="name" required></label>
            <label>Category<input name="category"></label>
            <label>Contact Person<input name="contact_person"></label>
            <label>Email<input type="email" name="email"></label>
            <label>Phone<input name="phone"></label>
            <label class="wide">Address<textarea name="address"></textarea></label>
            <label>Status<select name="status"><?= options($statuses, 'Active') ?></select></label>
            <button class="btn primary wide">Save Supplier</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
