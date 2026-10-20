CREATE DATABASE IF NOT EXISTS rpv_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rpv_management;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS activity_logs, generated_reports, customer_documents, inventory_history, purchase_order_items,
 purchase_orders, quotations, inquiries, projects, inventory_items, suppliers, customers, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(150),
 role ENUM('Admin','Sales','Purchasing','Inventory','Project Manager','Management','Staff') NOT NULL DEFAULT 'Staff',
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 account_id VARCHAR(30) NOT NULL UNIQUE,
 company_name VARCHAR(180) NOT NULL,
 customer_type VARCHAR(80),
 contact_person VARCHAR(150) NOT NULL,
 email VARCHAR(150), phone VARCHAR(50), billing_address TEXT,
 payment_terms VARCHAR(80), account_owner VARCHAR(150),
 status ENUM('Active','On hold','Completed','Inactive') DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE suppliers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 supplier_code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(180) NOT NULL, category VARCHAR(100), contact_person VARCHAR(150),
 email VARCHAR(150), phone VARCHAR(50), address TEXT,
 status ENUM('Active','Inactive','On hold') DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE inventory_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_code VARCHAR(50) NOT NULL UNIQUE,
 item_name VARCHAR(180) NOT NULL, category VARCHAR(100), unit VARCHAR(30),
 quantity INT NOT NULL DEFAULT 0, reserved_quantity INT NOT NULL DEFAULT 0,
 reorder_level INT NOT NULL DEFAULT 0, unit_cost DECIMAL(12,2) DEFAULT 0, location VARCHAR(120),
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE projects (
 id INT AUTO_INCREMENT PRIMARY KEY,
 project_code VARCHAR(40) NOT NULL UNIQUE,
 project_name VARCHAR(200) NOT NULL,
 customer_id INT NULL, department VARCHAR(100), manager VARCHAR(150),
 start_date DATE, end_date DATE, budget DECIMAL(14,2) DEFAULT 0,
 status ENUM('Planned','Ongoing','Completed','Delayed','Cancelled') DEFAULT 'Ongoing',
 progress INT DEFAULT 0, description TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE inquiries (
 id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL,
 subject VARCHAR(200) NOT NULL, details TEXT,
 status ENUM('Open','Pending','Resolved','Closed') DEFAULT 'Open', assigned_to VARCHAR(150),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE quotations (
 id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL,
 quotation_no VARCHAR(50) NOT NULL UNIQUE, description TEXT,
 total_amount DECIMAL(14,2) DEFAULT 0, valid_until DATE,
 status ENUM('Draft','Pending','Approved','Rejected','Expired') DEFAULT 'Pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE purchase_orders (
 id INT AUTO_INCREMENT PRIMARY KEY, po_number VARCHAR(50) NOT NULL UNIQUE,
 customer_id INT NULL, supplier_id INT NULL, order_date DATE,
 expected_delivery DATE, total_value DECIMAL(14,2) DEFAULT 0,
 status ENUM('Pending','Approved','In Transit','Received','Delayed','Cancelled') DEFAULT 'Pending',
 notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE purchase_order_items (
 id INT AUTO_INCREMENT PRIMARY KEY, purchase_order_id INT NOT NULL,
 item_id INT NULL, description VARCHAR(200), quantity INT NOT NULL DEFAULT 1,
 unit_price DECIMAL(12,2) DEFAULT 0,
 FOREIGN KEY(purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
 FOREIGN KEY(item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
);

CREATE TABLE inventory_history (
 id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL,
 transaction_type ENUM('Stock In','Stock Out') NOT NULL,
 quantity INT NOT NULL, reference_no VARCHAR(80), remarks VARCHAR(255), user_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE customer_documents (
 id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL,
 document_name VARCHAR(180) NOT NULL, document_type VARCHAR(80), file_reference VARCHAR(255),
 uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE generated_reports (
 id INT AUTO_INCREMENT PRIMARY KEY, report_name VARCHAR(180) NOT NULL,
 report_type VARCHAR(80), generated_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(generated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE activity_logs (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL,
 action VARCHAR(100) NOT NULL, description VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Password hashes are inserted by create_admin.py.
INSERT INTO customers(account_id,company_name,customer_type,contact_person,email,phone,billing_address,payment_terms,account_owner,status) VALUES
('CUS-1042','Our Lady of Fatima University - Antipolo','School','Sofia Roshiel Gelera','olfuant@gmail.com','+63123324128','Sumulong Highway Antipolo City','Net 30','Sofia Roshiel Gelera','Active'),
('CUS-1043','Company 1','Corporate','Contact 1','contact1@example.com','09170000001','Metro Manila','Net 30','Admin','Active'),
('CUS-1044','Company 2','Corporate','Contact 2','contact2@example.com','09170000002','Metro Manila','Net 15','Admin','On hold');

INSERT INTO suppliers(supplier_code,name,category,contact_person,email,phone,address,status) VALUES
('SUP-001','Supplier OLFU','Electrical','Tyra Silva','supplier1@example.com','09181111111','Quezon City','Active'),
('SUP-002','Supplier 2','Mechanical','Jessica Sicam','supplier2@example.com','09182222222','Makati City','Active'),
('SUP-003','Supplier 3','Tools','Asher Manaloto','supplier3@example.com','09183333333','Pasig City','Active');

INSERT INTO inventory_items(item_code,item_name,category,unit,quantity,reserved_quantity,reorder_level,unit_cost,location) VALUES
('ITM-001','Circuit Breaker 20A','Electrical','pc',40,5,10,350,'Warehouse A'),
('ITM-002','Copper Wire 2.0mm','Electrical','roll',8,2,10,1500,'Warehouse A'),
('ITM-003','PVC Conduit 20mm','Conduit','length',100,15,20,85,'Warehouse B');

INSERT INTO projects(project_code,project_name,customer_id,department,manager,start_date,end_date,budget,status,progress,description) VALUES
('PRJ-001','Electrical System Upgrade',1,'Engineering','Project Manager','2026-09-01','2026-12-15',250000,'Ongoing',55,'Upgrade of electrical distribution system'),
('PRJ-002','Preventive Maintenance',2,'Service','Project Manager','2026-08-01','2026-09-30',80000,'Completed',100,'Preventive maintenance services');

INSERT INTO inquiries(customer_id,subject,details,status,assigned_to) VALUES
(1,'Electrical system assessment','Request for site assessment and quotation','Open','Sales Staff');
INSERT INTO quotations(customer_id,quotation_no,description,total_amount,valid_until,status) VALUES
(1,'QT-2026-001','Electrical system upgrade quotation',100000,'2026-11-30','Pending');
INSERT INTO purchase_orders(po_number,customer_id,supplier_id,order_date,expected_delivery,total_value,status,notes) VALUES
('PO-2291',1,1,'2026-10-01','2026-10-08',55000,'Delayed','Awaiting supplier delivery'),
('PO-2290',2,2,'2026-09-28','2026-10-10',32000,'In Transit','In transit'),
('PO-2289',1,3,'2026-09-20','2026-09-27',18000,'Received','Complete');