<?php
// Run once from the command line:  php create_admin.php
// (In XAMPP: open the Shell and run  php create_admin.php  inside this folder.)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

require __DIR__ . '/config.php';
require __DIR__ . '/includes/db.php';

if (query_one('SELECT id FROM users WHERE username = ?', ['admin'])) {
    echo "Admin already exists.\n";
    exit;
}

execute(
    'INSERT INTO users(username, password_hash, full_name, email, role, status) VALUES(?,?,?,?,?,?)',
    ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'System Administrator', 'admin@rpv.local', 'Admin', 'Active']
);
echo "Created admin / admin123. Change the password after first login.\n";
