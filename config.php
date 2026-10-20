<?php
// Database settings. Edit these to match your MySQL setup.
// XAMPP's default MySQL user is "root" with an EMPTY password.
// You can also set them as environment variables (MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE).
$envPass = getenv('MYSQL_PASSWORD');

define('DB_HOST', getenv('MYSQL_HOST') ?: 'localhost');
define('DB_USER', getenv('MYSQL_USER') ?: 'root');
define('DB_PASS', $envPass !== false ? $envPass : '');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'rpv_management');
