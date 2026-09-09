-- ==========================================================================
-- TINDOC ACOUSTIC GUITARS — ACCOUNT PANEL ADD-ON TABLES
-- Run this AFTER your main schema (tindoc_db). It only adds the two
-- tables the customer account panel needs that weren't in the original
-- schema: cart_items and wishlist_items. Everything else (profile,
-- orders) reads from tables you already have (customers, orders,
-- order_items, products).
-- ==========================================================================

USE tindoc_db;

-- ==========================================================================
-- CART ITEMS
-- One row per product a logged-in customer currently has in their cart.
-- Composite unique key so "adding" a product already in the cart just
-- means updating quantity instead of inserting a duplicate row.
-- ==========================================================================
CREATE TABLE IF NOT EXISTS cart_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL DEFAULT 1,
  added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_cart_customer_product (customer_id, product_id),

  CONSTRAINT fk_cart_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_cart_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================================================
-- WISHLIST ITEMS
-- One row per product a customer has saved. No quantity — either it's
-- on the wishlist or it isn't.
-- ==========================================================================
CREATE TABLE IF NOT EXISTS wishlist_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_wishlist_customer_product (customer_id, product_id),

  CONSTRAINT fk_wishlist_customer
    FOREIGN KEY (customer_id) REFERENCES customers(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;
