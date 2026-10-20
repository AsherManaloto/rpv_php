<?php
require __DIR__ . '/includes/bootstrap.php';
login_required();

$statuses = ['Planned', 'Ongoing', 'Completed', 'Delayed', 'Cancelled'];

// ---------------- Form actions (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'delete') {
        admin_required();
    }
    handle_post(function () use ($action) {
        switch ($action) {
            case 'add':
                execute('INSERT INTO projects(project_code,project_name,customer_id,department,manager,start_date,end_date,budget,status,progress,description)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [post('project_code'), post('project_name'), post_or_null('customer_id'), post('department'), post('manager'),
                     post_or_null('start_date'), post_or_null('end_date'), post_num('budget'), post('status', 'Ongoing'),
                     post_num('progress'), post('description')]);
                log_activity('New project', post('project_name'));
                flash('Project added.');
                break;
            case 'edit':
                execute('UPDATE projects SET project_name=?,customer_id=?,department=?,manager=?,start_date=?,end_date=?,budget=?,status=?,progress=?,description=? WHERE id=?',
                    [post('project_name'), post_or_null('customer_id'), post('department'), post('manager'), post_or_null('start_date'),
                     post_or_null('end_date'), post_num('budget'), post('status'), post_num('progress'), post('description'),
                     (int) post('project_id', '0')]);
                flash('Project updated.');
                break;
            case 'delete':
                execute('DELETE FROM projects WHERE id=?', [(int) post('project_id', '0')]);
                flash('Project deleted.');
                break;
        }
        redirect('projects.php');
    }, 'projects.php', 'That project code already exists.');
}

// ---------------- Page data (GET) ----------------
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'All';
$dept = $_GET['department'] ?? 'All';

$sql = 'SELECT p.*, c.company_name AS customer_name FROM projects p LEFT JOIN customers c ON c.id=p.customer_id WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (p.project_code LIKE ? OR p.project_name LIKE ? OR c.company_name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status !== 'All') { $sql .= ' AND p.status = ?'; $params[] = $status; }
if ($dept !== 'All') { $sql .= ' AND p.department = ?'; $params[] = $dept; }
$sql .= ' ORDER BY p.created_at DESC';
$rows = query_all($sql, $params);

$customersList = query_all('SELECT id, company_name FROM customers ORDER BY company_name');
$departments = array_column(query_all('SELECT DISTINCT department FROM projects WHERE department IS NOT NULL ORDER BY department'), 'department');

$count = fn(string $s): int => (int) query_one($s)['total'];
$metrics = [
    'total'     => $count('SELECT COUNT(*) total FROM projects'),
    'ongoing'   => $count("SELECT COUNT(*) total FROM projects WHERE status='Ongoing'"),
    'completed' => $count("SELECT COUNT(*) total FROM projects WHERE status='Completed'"),
    'delayed'   => $count("SELECT COUNT(*) total FROM projects WHERE status='Delayed'"),
];

layout_start('Projects - RPV', 'projects');
?>
<div class="page-head"><h1>Project Management</h1><button class="btn primary" data-modal="projectAdd">+ Add Project</button></div>

<div class="cards four">
    <div class="metric"><small>Total</small><strong><?= $metrics['total'] ?></strong></div>
    <div class="metric"><small>On Going</small><strong><?= $metrics['ongoing'] ?></strong></div>
    <div class="metric"><small>Completed</small><strong><?= $metrics['completed'] ?></strong></div>
    <div class="metric"><small>Delayed</small><strong><?= $metrics['delayed'] ?></strong></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Search projects...">
    <select name="status"><option>All</option><?= options($statuses, $status) ?></select>
    <select name="department"><option>All</option><?= options($departments, $dept) ?></select>
    <button class="btn">Filter</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Code</th><th>Project</th><th>Customer</th><th>Department</th><th>Manager</th><th>Status</th><th>Progress</th><th>End Date</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td><?= h($p['project_code']) ?></td>
                <td><?= h($p['project_name']) ?></td>
                <td><?= dash($p['customer_name']) ?></td>
                <td><?= dash($p['department']) ?></td>
                <td><?= dash($p['manager']) ?></td>
                <td><span class="badge"><?= h($p['status']) ?></span></td>
                <td><?= h($p['progress']) ?>%</td>
                <td><?= dash($p['end_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8">No projects.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php layout_end(); ?>

<div class="modal" id="projectAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Project</h2>
        <form method="post" action="projects.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Project Code<input name="project_code" required></label>
            <label>Project Name<input name="project_name" required></label>
            <label>Customer
                <select name="customer_id">
                    <option value="">None</option>
                    <?php foreach ($customersList as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= h($c['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Department<input name="department"></label>
            <label>Manager<input name="manager"></label>
            <label>Start Date<input type="date" name="start_date"></label>
            <label>End Date<input type="date" name="end_date"></label>
            <label>Budget<input type="number" step="0.01" name="budget"></label>
            <label>Status<select name="status"><?= options($statuses, 'Ongoing') ?></select></label>
            <label>Progress %<input type="number" min="0" max="100" name="progress" value="0"></label>
            <label class="wide">Description<textarea name="description"></textarea></label>
            <button class="btn primary wide">Save Project</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
