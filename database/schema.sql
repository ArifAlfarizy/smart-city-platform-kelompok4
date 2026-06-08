-- MySQL Database Schema
CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

-- Users tabel
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

-- Client credentaials (IoT)
CREATE TABLE oauth_clients(
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     VARCHAR(100) NOT NULL UNIQUE,
  client_secret VARCHAR(255) NOT NULL,
  grant_types   VARCHAR(255),  -- "password,client_credentials,refresh_token"
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel refresh token
CREATE TABLE refresh_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  token_hash  VARCHAR(255) NOT NULL UNIQUE,
  is_revoked  TINYINT(1) DEFAULT 0,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE revoked_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  jti        VARCHAR(100) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);