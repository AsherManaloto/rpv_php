<?php
require __DIR__ . '/includes/bootstrap.php';
login_required();

$statuses = ['Active', 'On hold', 'Completed', 'Inactive'];

// ---------------- Form actions (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $cid = (int) post('customer_id', '0');
    $back = $cid ? "customers.php?id=$cid" : 'customers.php';

    handle_post(function () use ($action, $cid) {
        switch ($action) {
            case 'add':
                $id = execute('INSERT INTO customers
                    (account_id, company_name, customer_type, contact_person, email, phone, billing_address, payment_terms, account_owner, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [post('account_id'), post('company_name'), post('customer_type'), post('contact_person'), post('email'),
                     post('phone'), post('billing_address'), post('payment_terms'), post('account_owner'), post('status', 'Active')]);
                log_activity('New customer', post('company_name'));
                flash('Customer added.');
                redirect("customers.php?id=$id");

            case 'edit':
                execute('UPDATE customers SET company_name=?, customer_type=?, contact_person=?, email=?, phone=?,
                    billing_address=?, payment_terms=?, account_owner=?, status=? WHERE id=?',
                    [post('company_name'), post('customer_type'), post('contact_person'), post('email'), post('phone'),
                     post('billing_address'), post('payment_terms'), post('account_owner'), post('status'), $cid]);
                flash('Customer updated.');
                redirect("customers.php?id=$cid");

            case 'delete':
                admin_required();
                execute('DELETE FROM customers WHERE id=?', [$cid]);
                flash('Customer deleted.');
                redirect('customers.php');

            case 'inquiry_add':
                execute('INSERT INTO inquiries(customer_id, subject, details, status, assigned_to) VALUES (?,?,?,?,?)',
                    [$cid, post('subject'), post('details'), post('status', 'Open'), post('assigned_to')]);
                log_activity('New inquiry', post('subject'));
                flash('Inquiry added.');
                redirect("customers.php?id=$cid&tab=inquiries");

            case 'quotation_add':
                execute('INSERT INTO quotations(customer_id, quotation_no, description, total_amount, valid_until, status) VALUES (?,?,?,?,?,?)',
                    [$cid, post('quotation_no'), post('description'), post_num('total_amount'), post_or_null('valid_until'), post('status', 'Pending')]);
                log_activity('New quotation', post('quotation_no'));
                flash('Quotation added.');
                redirect("customers.php?id=$cid&tab=quotations");

            case 'po_add':
                execute('INSERT INTO purchase_orders(po_number, customer_id, supplier_id, order_date, expected_delivery, total_value, status, notes)
                    VALUES (?,?,?,?,?,?,?,?)',
                    [post('po_number'), $cid, post_or_null('supplier_id'), post_or_null('order_date') ?? date('Y-m-d'),
                     post_or_null('expected_delivery'), post_num('total_value'), post('status', 'Pending'), post('notes')]);
                log_activity('New purchase order', post('po_number'));
                flash('Purchase order added.');
                redirect("customers.php?id=$cid&tab=purchase_orders");

            case 'document_add':
                execute('INSERT INTO customer_documents(customer_id, document_name, document_type, file_reference) VALUES (?,?,?,?)',
                    [$cid, post('document_name'), post('document_type'), post('file_reference')]);
                flash('Document reference added.');
                redirect("customers.php?id=$cid&tab=documents");
        }
        redirect('customers.php');
    }, $back, 'That Account ID, quotation number or PO number already exists.');
}

// ---------------- Page data (GET) ----------------
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'All';

$sql = "SELECT c.*,
        COALESCE((SELECT SUM(total_amount) FROM quotations q WHERE q.customer_id=c.id AND q.status IN ('Pending','Approved')),0) AS open_value
        FROM customers c WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.company_name LIKE ? OR c.contact_person LIKE ? OR c.account_id LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($status !== 'All') {
    $sql .= ' AND c.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY c.updated_at DESC';
$rows = query_all($sql, $params);

$selected = null;
if (!empty($_GET['id'])) {
    $selected = query_one('SELECT * FROM customers WHERE id = ?', [(int) $_GET['id']]);
} elseif ($rows) {
    $selected = $rows[0];
}

$details = [];
if ($selected) {
    $id = $selected['id'];
    $details['inquiries']       = query_all('SELECT * FROM inquiries WHERE customer_id=? ORDER BY created_at DESC', [$id]);
    $details['quotations']      = query_all('SELECT * FROM quotations WHERE customer_id=? ORDER BY created_at DESC', [$id]);
    $details['purchase_orders'] = query_all('SELECT * FROM purchase_orders WHERE customer_id=? ORDER BY created_at DESC', [$id]);
    $details['projects']        = query_all('SELECT * FROM projects WHERE customer_id=? ORDER BY created_at DESC', [$id]);
    $details['documents']       = query_all('SELECT * FROM customer_documents WHERE customer_id=? ORDER BY uploaded_at DESC', [$id]);
}

layout_start('Customers - RPV', 'customers');
?>
<div class="page-head">
    <div>
        <p class="crumb">Home › Customers<?= $selected ? ' › ' . h($selected['company_name']) : '' ?></p>
        <h1>Customers</h1>
        <p><?= count($rows) ?> account(s) shown</p>
    </div>
    <button class="btn primary" data-modal="customerAdd">+ New Customer</button>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= h($search) ?>" placeholder="Search customers, contacts or IDs">
    <select name="status"><option>All</option><?= options($statuses, $status) ?></select>
    <button class="btn">Filter &amp; sort</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Customer</th><th>Contact</th><th>Status</th><th>Open value</th><th>Updated</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr class="clickable" onclick="location.href='customers.php?id=<?= (int) $c['id'] ?>'">
                <td><?= h($c['company_name']) ?></td>
                <td><?= h($c['contact_person']) ?></td>
                <td><span class="badge"><?= h($c['status']) ?></span></td>
                <td><?= money($c['open_value']) ?></td>
                <td><?= h($c['updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">No customers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($selected): ?>
<h4>SELECTED CUSTOMER</h4>
<section class="selected-card">
    <div>
        <h2><?= h($selected['company_name']) ?></h2>
        <p><?= h($selected['customer_type'] ?: 'Customer') ?> · Contact <?= h($selected['contact_person']) ?> · Customer since <?= date('Y', strtotime($selected['created_at'])) ?></p>
        <p><?= dash($selected['email']) ?> · <?= dash($selected['phone']) ?></p>
    </div>
    <span class="badge dark"><?= h($selected['status']) ?></span>
</section>

<div class="tabs" data-tabs>
    <button data-tab="info" class="active">Information</button>
    <button data-tab="inquiries">Inquiries</button>
    <button data-tab="quotations">Quotations</button>
    <button data-tab="purchase_orders">Purchase Orders</button>
    <button data-tab="projects">Projects</button>
    <button data-tab="documents">Documents</button>
</div>

<div class="tab-panel active" id="tab-info">
    <div class="detail-list">
        <div><span>Account ID</span><b><?= h($selected['account_id']) ?></b></div>
        <div><span>Primary contact</span><b><?= h($selected['contact_person']) ?></b></div>
        <div><span>Email</span><b><?= dash($selected['email']) ?></b></div>
        <div><span>Phone</span><b><?= dash($selected['phone']) ?></b></div>
        <div><span>Billing address</span><b><?= dash($selected['billing_address']) ?></b></div>
        <div><span>Payment terms</span><b><?= dash($selected['payment_terms']) ?></b></div>
        <div><span>Account owner</span><b><?= dash($selected['account_owner']) ?></b></div>
    </div>

    <button class="btn" data-modal="customerEdit">Edit Customer</button>
    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
    <form class="inline" method="post" action="customers.php" onsubmit="return confirm('Delete this customer and related records?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="customer_id" value="<?= (int) $selected['id'] ?>">
        <button class="btn danger">Delete</button>
    </form>
    <?php endif; ?>
</div>

<div class="tab-panel" id="tab-inquiries">
    <div class="section-head"><h3>Inquiries</h3><button class="btn primary" data-modal="inquiryAdd">+ Add Inquiry</button></div>
    <?php foreach ($details['inquiries'] as $x): ?>
        <div class="record"><b><?= h($x['subject']) ?></b><span><?= h($x['status']) ?> · <?= h($x['created_at']) ?></span><p><?= h($x['details']) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$details['inquiries']): ?><p class="muted">No inquiries.</p><?php endif; ?>
</div>

<div class="tab-panel" id="tab-quotations">
    <div class="section-head"><h3>Quotations</h3><button class="btn primary" data-modal="quotationAdd">+ Add Quotation</button></div>
    <?php foreach ($details['quotations'] as $x): ?>
        <div class="record"><b><?= h($x['quotation_no']) ?> — <?= money($x['total_amount']) ?></b><span><?= h($x['status']) ?> · Valid until <?= dash($x['valid_until']) ?></span><p><?= h($x['description']) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$details['quotations']): ?><p class="muted">No quotations.</p><?php endif; ?>
</div>

<div class="tab-panel" id="tab-purchase_orders">
    <div class="section-head"><h3>Purchase Orders</h3><button class="btn primary" data-modal="poAdd">+ Add Purchase Order</button></div>
    <?php foreach ($details['purchase_orders'] as $x): ?>
        <div class="record"><b><?= h($x['po_number']) ?> — <?= money($x['total_value']) ?></b><span><?= h($x['status']) ?> · <?= dash($x['order_date']) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$details['purchase_orders']): ?><p class="muted">No purchase orders.</p><?php endif; ?>
</div>

<div class="tab-panel" id="tab-projects">
    <?php foreach ($details['projects'] as $x): ?>
        <div class="record"><b><?= h($x['project_code']) ?> — <?= h($x['project_name']) ?></b><span><?= h($x['status']) ?> · <?= h($x['progress']) ?>%</span></div>
    <?php endforeach; ?>
    <?php if (!$details['projects']): ?><p class="muted">No projects.</p><?php endif; ?>
</div>

<div class="tab-panel" id="tab-documents">
    <div class="section-head"><h3>Documents</h3><button class="btn primary" data-modal="documentAdd">+ Add Reference</button></div>
    <?php foreach ($details['documents'] as $x): ?>
        <div class="record"><b><?= h($x['document_name']) ?></b><span><?= h($x['document_type'] ?: 'Document') ?> · <?= h($x['uploaded_at']) ?></span><p><?= h($x['file_reference']) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$details['documents']): ?><p class="muted">No document references.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php layout_end(); ?>

<div class="modal" id="customerAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>New Customer</h2>
        <form method="post" action="customers.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Account ID<input name="account_id" required></label>
            <label>Company Name<input name="company_name" required></label>
            <label>Customer Type<input name="customer_type"></label>
            <label>Contact Person<input name="contact_person" required></label>
            <label>Email<input type="email" name="email"></label>
            <label>Phone<input name="phone"></label>
            <label class="wide">Billing Address<textarea name="billing_address"></textarea></label>
            <label>Payment Terms<input name="payment_terms" placeholder="Net 30"></label>
            <label>Account Owner<input name="account_owner" value="<?= h($_SESSION['full_name']) ?>"></label>
            <label>Status<select name="status"><?= options($statuses, 'Active') ?></select></label>
            <button class="btn primary wide">Save Customer</button>
        </form>
    </div>
</div>

<?php if ($selected): $sid = (int) $selected['id']; ?>
<div class="modal" id="customerEdit">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Edit Customer</h2>
        <form method="post" action="customers.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="customer_id" value="<?= $sid ?>">
            <label>Company Name<input name="company_name" value="<?= h($selected['company_name']) ?>" required></label>
            <label>Type<input name="customer_type" value="<?= h($selected['customer_type']) ?>"></label>
            <label>Contact<input name="contact_person" value="<?= h($selected['contact_person']) ?>" required></label>
            <label>Email<input name="email" value="<?= h($selected['email']) ?>"></label>
            <label>Phone<input name="phone" value="<?= h($selected['phone']) ?>"></label>
            <label>Payment Terms<input name="payment_terms" value="<?= h($selected['payment_terms']) ?>"></label>
            <label class="wide">Address<textarea name="billing_address"><?= h($selected['billing_address']) ?></textarea></label>
            <label>Account Owner<input name="account_owner" value="<?= h($selected['account_owner']) ?>"></label>
            <label>Status<select name="status"><?= options($statuses, $selected['status']) ?></select></label>
            <button class="btn primary wide">Update</button>
        </form>
    </div>
</div>

<div class="modal" id="inquiryAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Inquiry</h2>
        <form method="post" action="customers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="inquiry_add">
            <input type="hidden" name="customer_id" value="<?= $sid ?>">
            <label>Subject<input name="subject" required></label>
            <label>Details<textarea name="details"></textarea></label>
            <label>Assigned To<input name="assigned_to"></label>
            <label>Status<select name="status"><?= options(['Open', 'Pending', 'Resolved', 'Closed']) ?></select></label>
            <button class="btn primary">Save</button>
        </form>
    </div>
</div>

<div class="modal" id="quotationAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Quotation</h2>
        <form method="post" action="customers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="quotation_add">
            <input type="hidden" name="customer_id" value="<?= $sid ?>">
            <label>Quotation No.<input name="quotation_no" required></label>
            <label>Description<textarea name="description"></textarea></label>
            <label>Total Amount<input type="number" step="0.01" name="total_amount"></label>
            <label>Valid Until<input type="date" name="valid_until"></label>
            <label>Status<select name="status"><?= options(['Pending', 'Draft', 'Approved', 'Rejected', 'Expired']) ?></select></label>
            <button class="btn primary">Save</button>
        </form>
    </div>
</div>

<div class="modal" id="poAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Purchase Order</h2>
        <form method="post" action="customers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="po_add">
            <input type="hidden" name="customer_id" value="<?= $sid ?>">
            <label>PO Number<input name="po_number" required></label>
            <label>Supplier ID<input type="number" name="supplier_id" placeholder="Optional"></label>
            <label>Order Date<input type="date" name="order_date"></label>
            <label>Expected Delivery<input type="date" name="expected_delivery"></label>
            <label>Total Value<input type="number" step="0.01" name="total_value"></label>
            <label>Status<select name="status"><?= options(['Pending', 'Approved', 'In Transit', 'Received', 'Delayed', 'Cancelled']) ?></select></label>
            <label>Notes<textarea name="notes"></textarea></label>
            <button class="btn primary">Save</button>
        </form>
    </div>
</div>

<div class="modal" id="documentAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Document Reference</h2>
        <form method="post" action="customers.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="document_add">
            <input type="hidden" name="customer_id" value="<?= $sid ?>">
            <label>Document Name<input name="document_name" required></label>
            <label>Document Type<input name="document_type"></label>
            <label>File/URL Reference<input name="file_reference"></label>
            <button class="btn primary">Save</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php page_end(); ?>
