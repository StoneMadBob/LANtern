ALTER TABLE devices
    ADD COLUMN ignore_active_issues TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
