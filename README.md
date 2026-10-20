# RPV Electrotech Power Systems, Inc.
## Online Outsourcing and Services Management System (PHP version)

Internal staff-only PHP + MySQL web application (converted from the Flask version).

### Setup with XAMPP (Windows)
1. Install XAMPP (PHP 8.0+). Open the XAMPP Control Panel and **Start** Apache and MySQL.
2. Copy this folder into `C:\xampp\htdocs\` (for example `C:\xampp\htdocs\rpv_php`).
3. Open `http://localhost/phpmyadmin` -> **Import** -> choose `schema.sql` -> **Go**.
   (Warning: `schema.sql` drops and recreates all tables, so only run it on a fresh setup.)
4. Check `config.php`. XAMPP's default is user `root` with an empty password.
   If your MySQL has a password, put it in `config.php`.
5. Create the first administrator. In the XAMPP Control Panel click **Shell**, then run:
   ```
   cd C:\xampp\htdocs\rpv_php
   php create_admin.php
   ```
6. Open `http://localhost/rpv_php/` and log in:
   - Username: `admin`
   - Password: `admin123`

Change the default password after the first login (Settings -> add a new admin, or edit the user).

### Without XAMPP
Run `php -S localhost:8000` inside this folder (needs PHP with the `pdo_mysql` extension), then open `http://localhost:8000`.

### Files
- `index.php` login, `logout.php`, `dashboard.php`, `customers.php`, `inventory.php`, `suppliers.php`, `projects.php`, `reports.php`, `settings.php`
- `includes/` shared code (database, layout, login/security helpers)
- `static/` CSS, JS and images
- `schema.sql` database structure and sample data
