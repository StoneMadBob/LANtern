ALTER TABLE announcements
    ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'General' AFTER body;