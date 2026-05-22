CREATE DATABASE IF NOT EXISTS noliktavas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE noliktavas;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('admin', 'item', 'shelf') NOT NULL DEFAULT 'shelf',
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);
