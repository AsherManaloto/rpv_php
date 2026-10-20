<?php
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');
    $user = query_one("SELECT * FROM users WHERE username = ? AND status = 'Active'", [$username]);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        redirect('dashboard.php');
    }
    flash('Invalid username/password or inactive account.', 'danger');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPV Login</title>
    <link rel="stylesheet" href="static/css/style.css">
</head>

<body class="login-body">
    <div class="login-card">
        <img class="login-logo" src="static/images/logo.png" alt="RPV logo">
        <h1>Electrotech Power Systems, Inc.</h1>
        <p class="login-sub">Authorized staff access only</p>

        <?php render_flashes(); ?>

        <form method="post">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input id="username" name="username" placeholder="Enter your username" autocomplete="username" required autofocus>

            <label for="password">Password</label>
            <div class="password-field">
                <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">Show</button>
            </div>

            <button type="submit" class="btn primary full">LOG IN</button>
        </form>

        <p class="login-footer">Forgot your password? Contact your administrator.</p>
        <small class="login-copy">© 2026 RPV Electrotech Power Systems, Inc.</small>
    </div>

    <script>
        const pw = document.getElementById('password');
        const toggle = document.getElementById('togglePassword');
        toggle.addEventListener('click', function () {
            const show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>
