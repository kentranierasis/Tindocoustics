-- ==========================================================================
-- TINDOC ACOUSTIC GUITARS — DATABASE SCHEMA
-- Run this in phpMyAdmin (Import tab, or paste into the SQL tab) to create
-- everything your admin panel and customer site need.
--
-- Every column here maps directly to something already visible in your
-- HTML: admproducts.php's table columns, admorders.php's table columns,
-- admcustomers.php's table columns, and the two login forms
-- (login.php customer modal + admin login page).
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS tindoc_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tindoc_db;

-- ==========================================================================
-- ADMINS
-- Matches admin login.php: Email Address, Password.
-- ==========================================================================
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)  NOT NULL DEFAULT 'Admin',
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================================================
-- CUSTOMERS
-- Matches login.php's Create Account form (Full Name, Email, Password)
-- and admcustomers.php's table (Email, Phone, Joined, Status).
-- Total Orders and Lifetime Spent are NOT stored here — they're
-- calculated on the fly from the orders table (see the example query
-- at the bottom of this file), since storing them would mean keeping
-- them in sync manually every time an order changes.
-- ==========================================================================
CREATE TABLE customers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  phone         VARCHAR(30)   DEFAULT NULL,
  status        ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================================================
-- PRODUCTS
-- Matches admproducts.php's table: Product name, SKU, Category, Price,
-- Stock, Status. The "admin-product-sold" text under each product name
-- (e.g. "Natural Spruce", "Cutaway") is really a short variant/description
-- subtitle, stored here as variant_label.
--
-- stock_status is intentionally a real column (not calculated) because
-- your UI lets an admin override it (e.g. mark something "Out of Stock"
-- for reasons unrelated to the exact count, like a manufacturing delay).
-- ==========================================================================
CREATE TABLE products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100)   NOT NULL,
  variant_label  VARCHAR(100)   DEFAULT NULL,   -- e.g. "Natural Spruce", "Cutaway"
  sku            VARCHAR(50)    NOT NULL UNIQUE,
  category       VARCHAR(60)    NOT NULL DEFAULT 'Acoustic Guitar',
  price          DECIMAL(10,2)  NOT NULL,
  stock_quantity INT UNSIGNED   NOT NULL DEFAULT 0,
  stock_status   ENUM('in-stock', 'low-stock', 'out-of-stock') NOT NULL DEFAULT 'in-stock',
  image_path     VARCHAR(255)   DEFAULT NULL,
  description    TEXT           DEFAULT NULL,
  created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================================================
-- ORDERS
-- Matches admorders.php's table: Order #, Customer, Date, Total,
-- Payment Method, Status.
-- ==========================================================================
CREATE TABLE orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number   VARCHAR(20)    NOT NULL UNIQUE,   -- e.g. "TIND-1047"
  customer_id    INT UNSIGNED   NOT NULL,
  order_date     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total          DECIMAL(10,2)  NOT NULL,
  payment_method ENUM('GCash', 'Bank Transfer', 'Credit Card') NOT NULL,
  status         ENUM('processing', 'shipped', 'delivered', 'pending', 'cancelled')
                 NOT NULL DEFAULT 'processing',

  CONSTRAINT fk_orders_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==========================================================================
-- ORDER ITEMS
-- Not visible as its own table in your UI yet, but every order needs to
-- know WHICH products were purchased and how many — this is what your
-- "Top Selling Products" dashboard panel and any future order-detail
-- view will read from.
-- ==========================================================================
CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED   NOT NULL,
  product_id   INT UNSIGNED   NOT NULL,
  quantity     INT UNSIGNED   NOT NULL DEFAULT 1,
  unit_price   DECIMAL(10,2)  NOT NULL,   -- snapshot of the price at purchase time

  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==========================================================================
-- MESSAGES
-- Matches admmessages.php's conversation list + chat thread (customer
-- name + message preview/body + timestamp).
-- customer_id is nullable so a message can come from a guest (someone
-- who messaged you before creating an account) without breaking.
-- sender_type distinguishes a customer's message from the admin's reply
-- within the same conversation thread, so the chat panel can render
-- "received" vs "sent" bubbles correctly.
-- ==========================================================================
CREATE TABLE messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED   DEFAULT NULL,
  sender_name VARCHAR(100)   NOT NULL,   -- filled even for guests
  sender_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  message     TEXT           NOT NULL,
  is_read     TINYINT(1)     NOT NULL DEFAULT 0,
  created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_messages_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================================================
-- SEED ADMIN ACCOUNT — you need to generate a real password hash first.
--
-- A bcrypt hash can only be generated correctly by the same language/
-- library that will later verify it. Since your login logic will use
-- PHP's password_verify(), the hash must come from PHP's password_hash()
-- — not a hash typed here by hand, which would just fail to match.
--
-- STEP 1: Create a throwaway file anywhere in your XAMPP htdocs folder,
-- e.g. htdocs/tindoc/make-hash.php, with this content:
--
--   <?php echo password_hash('admin123', PASSWORD_DEFAULT); ?>
--
-- STEP 2: Visit it in your browser (http://localhost/tindoc/make-hash.php).
-- It will print a long string starting with $2y$10$... — copy that exact
-- string.
--
-- STEP 3: Paste it into the INSERT below in place of PASTE_YOUR_HASH_HERE,
-- then run this whole file in phpMyAdmin.
--
-- STEP 4: Delete make-hash.php — never leave a password-hash generator
-- sitting on a live server.
-- ==========================================================================
INSERT INTO admins (full_name, email, password_hash) VALUES
('Admin', 'admin@tindocguitars.com', '$2y$10$Heb688ULnBDLsvg/G7Bqk.MSLbmeNrhV5yp2MElH6Ntl1pR/69EiS');

-- ==========================================================================
-- EXAMPLE: how Total Orders + Lifetime Spent get calculated for
-- admcustomers.php WITHOUT storing them as columns. Run this to see it
-- work once you have some rows in `orders`:
-- ==========================================================================
-- SELECT
--   c.id,
--   c.full_name,
--   c.email,
--   COUNT(o.id)         AS total_orders,
--   COALESCE(SUM(o.total), 0) AS lifetime_spent
-- FROM customers c
-- LEFT JOIN orders o ON o.customer_id = c.id
-- GROUP BY c.id;