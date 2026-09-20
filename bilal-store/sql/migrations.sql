-- =======================================================
--  E-commerce migration — safe to re-run.
--  Creates: categories, products tables.
--  Adds:    currency setting.
-- =======================================================

-- ---------------------------------------------------------
-- Categories
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(160) NOT NULL UNIQUE,
  `description` TEXT         DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Products
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `category_id`      INT UNSIGNED  DEFAULT NULL,
  `name`             VARCHAR(200)  NOT NULL,
  `slug`             VARCHAR(220)  NOT NULL UNIQUE,
  `sku`              VARCHAR(80)   DEFAULT NULL,
  `short_description` VARCHAR(500) DEFAULT NULL,
  `description`      TEXT          DEFAULT NULL,
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sale_price`       DECIMAL(10,2) DEFAULT NULL,
  `stock`            INT           NOT NULL DEFAULT 0,
  `image`            VARCHAR(255)  DEFAULT NULL,
  `status`           ENUM('active','inactive','draft') NOT NULL DEFAULT 'active',
  `featured`         TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status`   (`status`),
  KEY `idx_products_featured` (`featured`),
  CONSTRAINT `fk_products_category`
      FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Make SKU unique only when not null.
-- (Older MySQL versions don't support partial indexes, so we use a regular UNIQUE.)
-- We intentionally don't add a UNIQUE constraint on SKU so blank SKUs are allowed.

-- ---------------------------------------------------------
-- Orders
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_number`      VARCHAR(40)   NOT NULL UNIQUE,
  `customer_name`     VARCHAR(150)  NOT NULL,
  `customer_email`    VARCHAR(150)  NOT NULL,
  `customer_phone`    VARCHAR(40)   DEFAULT NULL,
  `shipping_address`  TEXT          NOT NULL,
  `shipping_city`     VARCHAR(100)  DEFAULT NULL,
  `shipping_zip`      VARCHAR(20)   DEFAULT NULL,
  `shipping_country`  VARCHAR(100)  DEFAULT NULL,
  `subtotal`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method`    VARCHAR(40)   NOT NULL DEFAULT 'cod',
  `status`            ENUM('pending','processing','shipped','completed','cancelled')
                      NOT NULL DEFAULT 'pending',
  `notes`             TEXT          DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_status`     (`status`),
  KEY `idx_orders_email`      (`customer_email`),
  KEY `idx_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Order items (line items, with product snapshot)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`       INT UNSIGNED  NOT NULL,
  `product_id`     INT UNSIGNED  DEFAULT NULL,
  `product_name`   VARCHAR(200)  NOT NULL,
  `product_sku`    VARCHAR(80)   DEFAULT NULL,
  `product_image`  VARCHAR(255)  DEFAULT NULL,
  `unit_price`     DECIMAL(10,2) NOT NULL,
  `quantity`       INT           NOT NULL,
  `line_total`     DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order`   (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Default settings additions
-- ---------------------------------------------------------
INSERT INTO `settings` (`key_name`, `value`) VALUES
  ('currency_code',   'USD'),
  ('currency_symbol', '$'),
  ('shipping_fee',    '0.00')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;
