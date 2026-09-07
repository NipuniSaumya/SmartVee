CREATE DATABASE IF NOT EXISTS smart_vehicle_parts;
USE smart_vehicle_parts;

-- ==========================================
-- USERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Cashier') NOT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- CATEGORIES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT
);

-- ==========================================
-- SUPPLIERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_no VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- CUSTOMERS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    vehicle_no VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- PRODUCTS TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    supplier_id INT,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    barcode VARCHAR(100),
    brand VARCHAR(100),
    purchase_price DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    stock_qty INT DEFAULT 0,
    reorder_level INT DEFAULT 5,
    product_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (category_id)
    REFERENCES categories(category_id)
    ON DELETE SET NULL,

    FOREIGN KEY (supplier_id)
    REFERENCES suppliers(supplier_id)
    ON DELETE SET NULL
);

-- ==========================================
-- GOODS RECEIVE NOTE (GRN)
-- ==========================================
CREATE TABLE IF NOT EXISTS grn (
    grn_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT,
    grn_date DATE NOT NULL,
    total_amount DECIMAL(12,2),
    created_by INT,

    FOREIGN KEY (supplier_id)
    REFERENCES suppliers(supplier_id),

    FOREIGN KEY (created_by)
    REFERENCES users(user_id)
);

-- ==========================================
-- GRN ITEMS
-- ==========================================
CREATE TABLE IF NOT EXISTS grn_items (
    grn_item_id INT AUTO_INCREMENT PRIMARY KEY,
    grn_id INT,
    product_id INT,
    quantity INT,
    unit_cost DECIMAL(10,2),
    subtotal DECIMAL(10,2),

    FOREIGN KEY (grn_id)
    REFERENCES grn(grn_id)
    ON DELETE CASCADE,

    FOREIGN KEY (product_id)
    REFERENCES products(product_id)
);
    
-- ==========================================
-- PURCHASE ORDERS (raised when stock is low)
-- ==========================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Sent','Received','Cancelled') DEFAULT 'Pending',

    FOREIGN KEY (supplier_id)
    REFERENCES suppliers(supplier_id)
);

-- ==========================================
-- PURCHASE ORDER ITEMS
-- ==========================================
CREATE TABLE IF NOT EXISTS purchase_order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    order_qty INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (po_id)
    REFERENCES purchase_orders(po_id)
    ON DELETE CASCADE,

    FOREIGN KEY (product_id)
    REFERENCES products(product_id)
);

-- ==========================================
-- PURCHASE ORDER LOGS
-- ==========================================
CREATE TABLE IF NOT EXISTS purchase_order_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NULL,
    action VARCHAR(100),
    action_date DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- SUPPLIER INVOICES (sent to admin by supplier for restock)
-- ==========================================
CREATE TABLE IF NOT EXISTS supplier_invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NULL,
    supplier_id INT,
    invoice_no VARCHAR(50) UNIQUE,
    invoice_date DATE,
    total_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (po_id)
    REFERENCES purchase_orders(po_id)
    ON DELETE SET NULL,

    FOREIGN KEY (supplier_id)
    REFERENCES suppliers(supplier_id)
);

-- ==========================================
-- SUPPLIER INVOICE ITEMS
-- ==========================================
CREATE TABLE IF NOT EXISTS supplier_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,

    FOREIGN KEY (invoice_id)
    REFERENCES supplier_invoices(invoice_id)
    ON DELETE CASCADE,

    FOREIGN KEY (product_id)
    REFERENCES products(product_id)
);

-- ==========================================
-- SALES TABLE
-- ==========================================
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE,
    customer_id INT NULL,
    cashier_id INT,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2),
    discount DECIMAL(12,2) DEFAULT 0,
    grand_total DECIMAL(12,2),
    payment_method ENUM('Cash','Card','QR'),
    amount_paid DECIMAL(12,2),
    balance DECIMAL(12,2),

    FOREIGN KEY (customer_id)
    REFERENCES customers(customer_id)
    ON DELETE SET NULL,

    FOREIGN KEY (cashier_id)
    REFERENCES users(user_id)
);

-- ==========================================
-- SALE ITEMS
-- ==========================================
CREATE TABLE IF NOT EXISTS sale_items (
    sale_item_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT,
    product_id INT,
    quantity INT,
    unit_price DECIMAL(10,2),
    subtotal DECIMAL(10,2),

    FOREIGN KEY (sale_id)
    REFERENCES sales(sale_id)
    ON DELETE CASCADE,

    FOREIGN KEY (product_id)
    REFERENCES products(product_id)
);

-- ==========================================
-- STOCK TRANSACTIONS
-- ==========================================
CREATE TABLE IF NOT EXISTS stock_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    transaction_type ENUM('IN','OUT'),
    quantity INT,
    reference_no VARCHAR(100),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (product_id)
    REFERENCES products(product_id)
);

-- ==========================================
-- SYSTEM SETTINGS
-- ==========================================
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    shop_name VARCHAR(150),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    logo VARCHAR(255)
);

-- ==========================================
-- DEFAULT ADMIN ACCOUNT
-- ==========================================
INSERT INTO users
(full_name, username, password, role)
VALUES
(
'System Administrator',
'admin',
'$2y$10$9wefOrEPNeF/SAAyXGGMfOi53pTtVMWIswulwIRyHi7p.x/..Tu3y',
'Admin'
) AS new_users
ON DUPLICATE KEY UPDATE 
    full_name = new_users.full_name, 
    password = new_users.password, 
    role = new_users.role;

-- ==========================================
-- SAMPLE CATEGORIES
-- ==========================================
INSERT INTO categories (category_id, category_name)
VALUES
(1, 'Engine Parts'),
(2, 'Brake Parts'),
(3, 'Electrical Parts'),
(4, 'Filters'),
(5, 'Accessories') AS new_categories
ON DUPLICATE KEY UPDATE category_name = new_categories.category_name;

-- ==========================================
-- SAMPLE SUPPLIER
-- ==========================================
INSERT INTO suppliers
(supplier_id, supplier_name, contact_no, email)
VALUES
(1, 'ABC Auto Parts', '0771234567', 'abc@gmail.com') AS new_suppliers
ON DUPLICATE KEY UPDATE 
    supplier_name = new_suppliers.supplier_name, 
    contact_no = new_suppliers.contact_no, 
    email = new_suppliers.email;

