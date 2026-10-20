<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

function h($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $category = 'success'): void
{
    $_SESSION['flash'][] = [$category, $message];
}

// Trimmed form value (use $_POST directly for passwords).
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function post_or_null(string $key): ?string
{
    $v = post($key);
    return $v === '' ? null : $v;
}

// Numeric field: empty becomes 0
function post_num(string $key)
{
    $v = post($key);
    return $v === '' ? 0 : $v;
}

function money($n): string
{
    return '₱' . number_format((float) $n, 2);
}

function dash($v): string
{
    return ($v === null || $v === '') ? '-' : h($v);
}

// Builds <option> tags; marks the one equal to $current as selected.
function options(array $list, $current = null): string
{
    $out = '';
    foreach ($list as $v) {
        $out .= '<option' . ((string) $v === (string) $current ? ' selected' : '') . '>' . h($v) . '</option>';
    }
    return $out;
}

// ---- CSRF protection for every POST form ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid or expired form. Please go back, refresh the page and try again.');
    }
}

// ---- Authentication ----
function login_required(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('index.php');
    }
}

function admin_required(): void
{
    login_required();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        forbidden();
    }
}

function forbidden(): void
{
    http_response_code(403);
    layout_start('403 - RPV');
    echo '<div class="empty-state"><h1>403</h1><p>You do not have permission to access this page.</p>'
        . '<a class="btn primary" href="dashboard.php">Return to dashboard</a></div>';
    layout_end();
    page_end();
    exit;
}

function log_activity(string $action, string $description): void
{
    if (!empty($_SESSION['user_id'])) {
        execute('INSERT INTO activity_logs(user_id, action, description) VALUES(?,?,?)',
            [$_SESSION['user_id'], $action, $description]);
    }
}

// Runs a POST handler; on a database error shows a friendly message and redirects.
function handle_post(callable $fn, string $fallback, ?string $duplicateMsg = null): void
{
    try {
        $fn();
    } catch (PDOException $e) {
        $code = $e->errorInfo[1] ?? 0;
        if ($code === 1062) {
            $msg = $duplicateMsg ?? 'That code or number already exists.';
        } else {
            $msg = 'Could not save. Please check the values you entered.';
        }
        flash($msg, 'danger');
        redirect($fallback);
    }
}
