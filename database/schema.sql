-- Tambahkan di database/schema.sql
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

-- Tabel traffic_data
CREATE TABLE traffic_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    road_name VARCHAR(100) NOT NULL DEFAULT 'Jalan MT Haryono',
    vehicle_count INT NOT NULL,
    average_speed DECIMAL(5,2) NOT NULL,
    congestion_level VARCHAR(20) NOT NULL,
    observation_time TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS zones (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50)     NOT NULL,
    district   VARCHAR(100)    NOT NULL,
    area_km2   DECIMAL(8,2)    DEFAULT NULL,
    created_at TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS environment_data (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id         VARCHAR(50)     NOT NULL,
    zone              ENUM('A','B','C','D','E') NOT NULL,

    aqi               DECIMAL(7,2)    NOT NULL COMMENT 'Air Quality Index',
    aqi_status        VARCHAR(30)     DEFAULT NULL COMMENT 'Baik/Sedang/Tidak Sehat/dll',
    pm25              DECIMAL(7,2)    DEFAULT NULL,
    pm10              DECIMAL(7,2)    DEFAULT NULL,
    no2               DECIMAL(7,2)    DEFAULT NULL,
    co                DECIMAL(7,2)    DEFAULT NULL,
    o3                DECIMAL(7,2)    DEFAULT NULL,

    temperature       DECIMAL(5,2)    NOT NULL COMMENT 'Celsius',
    humidity          DECIMAL(5,2)    NOT NULL COMMENT 'Percentage 0-100',

    rain_level        DECIMAL(5,2)    DEFAULT 0   COMMENT 'Persentase curah hujan 0-100',
    rain_intensity    DECIMAL(5,2)    DEFAULT 0   COMMENT 'Intensitas mm/h',
    rain_status       VARCHAR(30)     DEFAULT NULL COMMENT 'Tidak Hujan/Ringan/Sedang/Lebat',

    flood_level       DECIMAL(7,2)    NOT NULL DEFAULT 0 COMMENT 'centimeter',
    flood_status      VARCHAR(20)     DEFAULT NULL COMMENT 'Aman/Waspada/Siaga/Bahaya',

    recorded_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_zone            (zone),
    INDEX idx_recorded_at     (recorded_at),
    INDEX idx_zone_recorded   (zone, recorded_at),
    INDEX idx_sensor_id       (sensor_id)
) ENGINE=InnoDB COMMENT='Pembacaan sensor lingkungan per zona (v2 + rain + vehicle)';

CREATE TABLE IF NOT EXISTS vehicle_counts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id       VARCHAR(50)     NOT NULL,
    zone            ENUM('A','B','C','D','E') NOT NULL,

    vehicle_count   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Jumlah kendaraan per interval',
    interval_sec    INT UNSIGNED    NOT NULL DEFAULT 60 COMMENT 'Durasi pengamatan (detik)',
    traffic_status  VARCHAR(20)     DEFAULT NULL COMMENT 'Lancar/Sedang/Padat/Macet',

    rain_intensity  DECIMAL(5,2)    DEFAULT 0   COMMENT 'Intensitas hujan saat itu mm/h',
    rain_status     VARCHAR(30)     DEFAULT NULL,
    flood_level     DECIMAL(7,2)    DEFAULT 0,

    recorded_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_zone            (zone),
    INDEX idx_recorded_at     (recorded_at),
    INDEX idx_zone_recorded   (zone, recorded_at),
    INDEX idx_traffic_status  (traffic_status)
) ENGINE=InnoDB COMMENT='Hitungan kendaraan dari IR sensor per zona per interval';

CREATE TABLE IF NOT EXISTS environment_alerts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone        ENUM('A','B','C','D','E') NOT NULL,
    alert_type  ENUM('AQI_HIGH','FLOOD_HIGH','TEMP_EXTREME','HUMIDITY_EXTREME','RAIN_HEAVY') NOT NULL,
    value       VARCHAR(50)     NOT NULL COMMENT 'Nilai yang memicu alert',
    threshold   VARCHAR(50)     NOT NULL COMMENT 'Nilai ambang batas',
    message     TEXT            NOT NULL,
    severity    ENUM('WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
    status      ENUM('active','resolved') NOT NULL DEFAULT 'active',
    resolved_at TIMESTAMP       NULL DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_zone          (zone),
    INDEX idx_status        (status),
    INDEX idx_alert_type    (alert_type),
    INDEX idx_zone_status   (zone, status)
) ENGINE=InnoDB COMMENT='Alert otomatis berdasarkan pembacaan sensor';