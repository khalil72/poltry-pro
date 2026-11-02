-- Adds forgotten-password support to a database created before this date. Safe to run once.
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER must_change_password;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email VARCHAR(160) NOT NULL,
  token_hash CHAR(64) NULL UNIQUE COMMENT 'SHA-256 of the emailed token. NULL when the email matched no account.',
  ip_address VARCHAR(45) NOT NULL,
  requested_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  KEY idx_reset_email (email, requested_at),
  KEY idx_reset_ip (ip_address, requested_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
