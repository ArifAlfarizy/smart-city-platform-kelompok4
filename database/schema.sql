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

-- Client credentials (IoT)
CREATE TABLE oauth_clients(
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     VARCHAR(100) NOT NULL UNIQUE,
  client_secret VARCHAR(255) NOT NULL,
  grant_types   VARCHAR(255),
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

CREATE TABLE citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nik VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    road_name VARCHAR(100) NOT NULL,
    category ENUM(
        'accident',
        'broken_vehicle',
        'fallen_tree',
        'flood',
        'road_obstacle',
        'traffic_light_damage'
    ) NOT NULL,
    description TEXT NOT NULL,
    status ENUM(
        'pending',
        'process',
        'completed'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id)
        ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS traffic_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  road_name VARCHAR(100) NOT NULL,
  vehicle_count INT NOT NULL,
  average_speed DECIMAL(5,2) NOT NULL,
  congestion_level ENUM('Normal', 'Padat', 'Macet', 'Sangat Macet') NOT NULL,
  observation_time TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  road_name VARCHAR(100) NOT NULL,
  incident_type ENUM('accident', 'broken_vehicle', 'fallen_tree', 'flood', 'road_obstacle', 'traffic_light_damage') NOT NULL,
  description TEXT NOT NULL,
  status ENUM('active', 'resolved') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_road_name (road_name),
  INDEX idx_status (status)
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

-- Seed: dummy data
INSERT INTO environment_data 
    (sensor_id, zone, aqi, temperature, humidity, flood_level, pm25, pm10, no2, co, o3)
VALUES
    ('ESP32-A-01', 'A', 85.5,  31.2, 72.1, 5.0,   35.2, 48.1, 42.3, 1.2, 55.0),
    ('ESP32-A-01', 'A', 92.0,  32.0, 74.5, 6.5,   40.1, 55.0, 45.0, 1.5, 60.0),
    ('ESP32-B-01', 'B', 110.3, 33.5, 80.0, 52.0,  55.0, 72.0, 62.0, 2.1, 70.0),
    ('ESP32-B-01', 'B', 75.2,  30.0, 68.3, 10.0,  28.3, 39.5, 35.0, 0.9, 45.0),
    ('ESP32-C-01', 'C', 60.1,  29.5, 65.0, 3.0,   22.1, 30.5, 28.0, 0.7, 38.0),
    ('ESP32-C-01', 'C', 130.7, 35.0, 88.0, 75.0,  68.0, 90.0, 80.0, 3.0, 95.0),
    ('ESP32-D-01', 'D', 95.0,  31.8, 75.5, 20.0,  42.0, 58.0, 48.0, 1.6, 62.0),
    ('ESP32-E-01', 'E', 55.0,  28.5, 60.0, 0.0,   18.0, 25.0, 22.0, 0.5, 30.0);

INSERT INTO environment_alerts (zone, alert_type, value, threshold, message, severity, status) VALUES
    ('B', 'AQI_HIGH',   '110.3', '100', 'AQI melebihi batas aman di Zona B. Warga disarankan menggunakan masker.', 'WARNING',  'active'),
    ('B', 'FLOOD_HIGH', '52.0',  '50',  'Ketinggian air mencapai 52 cm di Zona B. Potensi banjir tinggi.',         'CRITICAL', 'active'),
    ('C', 'AQI_HIGH',   '130.7', '100', 'AQI sangat buruk di Zona C. Hindari aktivitas luar ruangan.',             'CRITICAL', 'active');

-- Tabel untuk hasil analisis ML
CREATE TABLE IF NOT EXISTS ml_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_type VARCHAR(50) NOT NULL DEFAULT 'congestion',
    zone VARCHAR(100) DEFAULT 'MT_Haryono',
    input_data JSON NOT NULL,
    result JSON NOT NULL,
    confidence_score DECIMAL(5,4) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_model_type (model_type),
    INDEX idx_created_at (created_at),
    INDEX idx_zone (zone)
);