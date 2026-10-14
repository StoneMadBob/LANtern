CREATE TABLE speedtest_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
