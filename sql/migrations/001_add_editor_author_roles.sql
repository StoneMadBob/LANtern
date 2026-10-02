ALTER TABLE users
    MODIFY role ENUM('admin','editor','author','user') NOT NULL DEFAULT 'user';