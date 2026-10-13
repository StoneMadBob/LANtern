CREATE TABLE ping_results (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) NOT NULL,
    latency_ms DECIMAL(8, 3) DEFAULT NULL,
    INDEX idx_ping_results_device_time (device_id, checked_at),
    INDEX idx_ping_results_checked_at (checked_at),
    CONSTRAINT fk_ping_results_device
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);
