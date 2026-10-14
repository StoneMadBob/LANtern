ALTER TABLE speedtest_results
    ADD COLUMN test_type ENUM('manual','automatic') NOT NULL DEFAULT 'manual' AFTER checked_at;
