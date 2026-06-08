CREATE TABLE users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          
  photo     VARCHAR(255) NULL,
  oauth_provider ENUM('google', 'github') NULL,
  oauth_id VARCHAR(255) NULL,
  role        ENUM('citizen', 'operator') NOT NULL DEFAULT 'citizen',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,    
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_email (email),
  INDEX idx_role  (role)
);