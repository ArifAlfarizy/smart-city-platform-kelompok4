-- MySQL Database Schema
CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

-- ========================================================
-- AUTH SERVER TABLES (ROLE 1)
-- ========================================================

-- Users tabel
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          
  photo         VARCHAR(255) NULL,
  oauth_provider ENUM('google', 'github') NULL,
  oauth_id      VARCHAR(255) NULL,
  role          ENUM('citizen', 'operator') NOT NULL DEFAULT 'citizen',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,    
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
);

-- Client credentials (IoT)
CREATE TABLE IF NOT EXISTS oauth_clients(
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     VARCHAR(100) NOT NULL UNIQUE,
  client_secret VARCHAR(255) NOT NULL,
  grant_types   VARCHAR(255),  -- "password,client_credentials,refresh_token"
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel refresh token
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT,
  token_hash VARCHAR(255) NOT NULL UNIQUE,
  is_revoked TINYINT(1) DEFAULT 0,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel revoked token
CREATE TABLE IF NOT EXISTS revoked_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  jti        VARCHAR(100) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================================
-- ML SERVICE TABLES (ROLE 5)
-- ========================================================

-- Tabel ml_predictions
CREATE TABLE IF NOT EXISTS ml_predictions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    model_type       ENUM('traffic', 'aqi', 'anomaly') NOT NULL,
    zone             VARCHAR(10)  DEFAULT NULL,
    input_data       JSON         NOT NULL,
    result           JSON         NOT NULL,
    confidence_score DECIMAL(5,2) DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_model_type (model_type),
    INDEX idx_created_at (created_at)
);

-- ========================================================
-- TRAFFIC SERVICE TABLES (ROLE 3)
-- ========================================================

-- Tabel traffic_data
CREATE TABLE IF NOT EXISTS traffic_data (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  sensor_id        VARCHAR(50) NOT NULL,
  zone             ENUM('A', 'B', 'C') NOT NULL,
  vehicle_count    INT NOT NULL,
  avg_speed        DECIMAL(5,2) NOT NULL,
  congestion_level INT NOT NULL,
  recorded_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_traffic_zone (zone),
  INDEX idx_traffic_recorded_at (recorded_at)
);

-- Tabel incidents
CREATE TABLE IF NOT EXISTS incidents (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  zone          ENUM('A', 'B', 'C') NOT NULL,
  incident_type VARCHAR(100) NOT NULL,
  description   TEXT NOT NULL,
  status        ENUM('active', 'resolved') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_incidents_zone (zone),
  INDEX idx_incidents_status (status)
);