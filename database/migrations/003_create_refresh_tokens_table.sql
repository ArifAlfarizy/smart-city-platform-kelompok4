CREATE TABLE refresh_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  token_hash  VARCHAR(255) NOT NULL UNIQUE,
  is_revoked  TINYINT(1) DEFAULT 0,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);