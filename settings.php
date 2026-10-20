<?php
require __DIR__ . '/includes/bootstrap.php';
admin_required();

$roles = ['Admin', 'Sales', 'Purchasing', 'Inventory', 'Project Manager', 'Management', 'Staff'];

// ---------------- Form actions (POST) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(function () use ($roles) {
        $userId = (int) post('user_id', '0');
        $password = (string) ($_POST['password'] ?? '');
        switch (post('action')) {
            case 'add':
                if ($password === '') {
                    flash('Password is required.', 'danger');
                    break;
                }
                execute('INSERT INTO users(username,password_hash,full_name,email,role,status) VALUES (?,?,?,?,?,?)',
                    [post('username'), password_hash($password, PASSWORD_DEFAULT), post('full_name'), post('email'),
                     post('role', 'Staff'), post('status', 'Active')]);
                flash('User added.');
                break;

            case 'edit':
                if ($password !== '') {
                    execute('UPDATE users SET full_name=?,email=?,role=?,status=?,password_hash=? WHERE id=?',
                        [post('full_name'), post('email'), post('role'), post('status'), password_hash($password, PASSWORD_DEFAULT), $userId]);
                } else {
                    execute('UPDATE users SET full_name=?,email=?,role=?,status=? WHERE id=?',
                        [post('full_name'), post('email'), post('role'), post('status'), $userId]);
                }
                flash('User updated.');
                break;

            case 'delete':
                if ($userId === (int) $_SESSION['user_id']) {
                    flash('You cannot delete your own logged-in account.', 'danger');
                } else {
                    execute('DELETE FROM users WHERE id=?', [$userId]);
                    flash('User deleted.');
                }
                break;
        }
        redirect('settings.php');
    }, 'settings.php', 'Could not add user. Username may already exist.');
}

// ---------------- Page data (GET) ----------------
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'All';

$sql = 'SELECT id,username,full_name,email,role,status,created_at FROM users WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status !== 'All') { $sql .= ' AND status = ?'; $params[] = $status; }
$sql .= ' ORDER BY full_name';
$users = query_all($sql, $params);

$count = fn(string $s): int => (int) query_one($s)['total'];
$metrics = [
    'total'    => $count('SELECT COUNT(*) total FROM users'),
    'active'   => $count("SELECT COUNT(*) total FROM users WHERE status='Active'"),
    'inactive' => $count("SELECT COUNT(*) total FROM users WHERE status='Inactive'"),
];

layout_start('Settings - RPV', 'settings');
?>
<div class="page-head">
    <div><h1>Settings</h1><p>Account settings</p></div>
    <button class="btn primary" data-modal="userAdd">+ Add User</button>
</div>

<div class="cards three">
    <div class="metric"><small>Total</small><strong><?= $metrics['total'] ?></strong></div>
    <div class="metric"><small>Active</small><strong><?= $metrics['active'] ?></strong></div>
    <div class="metric"><small>Inactive</small><strong><?= $metrics['inactive'] ?></strong></div>
</div>

<form class="filters" method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Search users...">
    <select name="status"><option>All</option><?= options(['Active', 'Inactive'], $status) ?></select>
    <button class="btn">Filter</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= h($u['username']) ?></td>
                <td><?= h($u['full_name']) ?></td>
                <td><?= dash($u['email']) ?></td>
                <td><?= h($u['role']) ?></td>
                <td><span class="badge"><?= h($u['status']) ?></span></td>
                <td><?= h($u['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="6">No users found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php layout_end(); ?>

<div class="modal" id="userAdd">
    <div class="modal-card">
        <button class="close">×</button>
        <h2>Add Staff User</h2>
        <form method="post" action="settings.php" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <label>Username<input name="username" required></label>
            <label>Password<input type="password" name="password" required></label>
            <label>Full Name<input name="full_name" required></label>
            <label>Email<input type="email" name="email"></label>
            <label>Role<select name="role"><?= options($roles, 'Staff') ?></select></label>
            <label>Status<select name="status"><option>Active</option><option>Inactive</option></select></label>
            <button class="btn primary wide">Create User</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
