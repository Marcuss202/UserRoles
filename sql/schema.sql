CREATE DATABASE IF NOT EXISTS noliktavas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE noliktavas;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    password_changed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('admin', 'item', 'shelf') NOT NULL DEFAULT 'shelf',
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    shelf VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    INDEX idx_name (name),
    INDEX idx_shelf (shelf),
    INDEX idx_created_by (created_by),
    INDEX idx_updated_by (updated_by),
    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    field_name VARCHAR(100) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_time (created_at),
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('created', 'accepted', 'fulfilled') NOT NULL DEFAULT 'created',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_by INT NULL,
    accepted_at TIMESTAMP NULL,
    fulfilled_by INT NULL,
    fulfilled_at TIMESTAMP NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created_at (created_at),
    CONSTRAINT fk_orders_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_orders_accepted_by
        FOREIGN KEY (accepted_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_orders_fulfilled_by
        FOREIGN KEY (fulfilled_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);
