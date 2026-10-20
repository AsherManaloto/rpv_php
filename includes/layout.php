<?php
function render_flashes(): void
{
    foreach ($_SESSION['flash'] ?? [] as [$category, $message]) {
        echo '<div class="flash ' . h($category) . '">' . h($message) . '</div>';
    }
    unset($_SESSION['flash']);
}

function layout_start(string $title = 'RPV Management System', string $active = ''): void
{
    $user = $_SESSION['full_name'] ?? '';
    $role = $_SESSION['role'] ?? '';
    $items = [
        'dashboard' => ['dashboard.php', '⌂ Dashboard'],
        'customers' => ['customers.php', '▣ Customers'],
        'inventory' => ['inventory.php', '▦ Inventory'],
        'suppliers' => ['suppliers.php', 'ጸ Suppliers'],
        'projects'  => ['projects.php', '▤ Projects'],
        'reports'   => ['reports.php', '▥ Reports'],
    ];
    if ($role === 'Admin') {
        $items['settings'] = ['settings.php', '⚙ Settings'];
    }
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="static/css/style.css">
  <script defer src="static/js/app.js"></script>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <div class="logo-box"><img src="static/images/logo.png" alt="RPV logo"></div>
      <div><small>ELECTROTECH<br>POWER SYSTEMS INC.</small></div>
    </div>
    <nav>
      <?php foreach ($items as $key => [$href, $label]): ?>
        <a class="<?= $key === $active ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">Internal Staff System</div>
  </aside>
  <div class="main-wrap">
    <header class="topbar">
      <form class="global-search" action="customers.php" method="get"><input name="q" placeholder="Search..."></form>
      <div class="user-menu"><span class="avatar">●</span><div><strong><?= h($user ?: 'Staff') ?></strong><small><?= h($role) ?></small></div><a href="logout.php">Log out</a></div>
    </header>
    <main class="content">
      <?php render_flashes(); ?>
<?php
}

function layout_end(): void
{
    echo "\n    </main>\n  </div>\n</div>\n";
}

function page_end(): void
{
    echo "</body>\n</html>\n";
}
