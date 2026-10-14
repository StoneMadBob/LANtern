-- --------------------------------------------------
-- LANtern Database Schema
-- --------------------------------------------------

-- -------------------------
-- Users
-- -------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','author','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------
-- Signup Rate Limiting
-- -------------------------
CREATE TABLE signup_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signup_attempts_ip_time (ip_address, attempted_at)
);

-- -------------------------
-- Application Settings
-- -------------------------
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------
-- Login Rate Limiting
-- -------------------------
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_user_time (ip_address, username, attempted_at)
);

-- -------------------------
-- Links
-- -------------------------
CREATE TABLE links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    host VARCHAR(100) DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    `group` VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0
);

-- -------------------------
-- Announcements
-- -------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------
-- Uploads
-- -------------------------
CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------
-- Knowledge Base Articles
-- -------------------------
CREATE TABLE kb_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------
-- Knowledge Base Tags
-- -------------------------
CREATE TABLE kb_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- -------------------------
-- KB Article ↔ Tag Mapping
-- -------------------------
CREATE TABLE kb_article_tags (
    article_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES kb_tags(id) ON DELETE CASCADE
);

-- -------------------------
-- Devices (Manage Devices)
-- -------------------------
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(100) NOT NULL,
    hostname VARCHAR(255) DEFAULT NULL,
    ip VARCHAR(50) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    `group` VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('online','offline','maintenance') DEFAULT 'offline',
    ignore_active_issues TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------
-- Device Ping History
-- -------------------------
CREATE TABLE ping_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) NOT NULL,
    latency_ms DECIMAL(8, 3) DEFAULT NULL,
    INDEX idx_ping_results_device_time (device_id, checked_at),
    INDEX idx_ping_results_checked_at (checked_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- -------------------------
-- Ookla Speed Test History
-- -------------------------
CREATE TABLE speedtest_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    test_type ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
    download_mbps DECIMAL(12, 3) DEFAULT NULL,
    upload_mbps DECIMAL(12, 3) DEFAULT NULL,
    ping_ms DECIMAL(10, 3) DEFAULT NULL,
    jitter_ms DECIMAL(10, 3) DEFAULT NULL,
    packet_loss DECIMAL(7, 4) DEFAULT NULL,
    server_name VARCHAR(150) DEFAULT NULL,
    server_location VARCHAR(150) DEFAULT NULL,
    result_url VARCHAR(500) DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    INDEX idx_speedtest_results_checked_at (checked_at)
);

-- --------------------------------------------------
-- Default Admin User (optional)
-- --------------------------------------------------
-- INSERT INTO users (username, password_hash, role)
-- VALUES ('admin', 'PASSWORD_HASH_HERE', 'admin');
