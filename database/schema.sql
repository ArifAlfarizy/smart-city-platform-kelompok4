CREATE DATABASE IF NOT EXISTS smartcity;
USE smartcity;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    photo         VARCHAR(255)  NULL,
    oauth_provider ENUM('google','github') NULL,
    oauth_id      VARCHAR(255)  NULL,
    role          ENUM('citizen','operator') NOT NULL DEFAULT 'citizen',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
);

CREATE TABLE IF NOT EXISTS oauth_clients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    client_id     VARCHAR(100)  NOT NULL UNIQUE,
    client_secret VARCHAR(255)  NOT NULL,
    grant_types   VARCHAR(255),
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    token_hash  VARCHAR(255)  NOT NULL UNIQUE,
    is_revoked  TINYINT(1)    DEFAULT 0,
    expires_at  TIMESTAMP     NOT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS revoked_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    jti        VARCHAR(100)  NOT NULL UNIQUE,
    expires_at TIMESTAMP     NOT NULL,
    revoked_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS citizens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT           NOT NULL,
    nik        VARCHAR(20)   UNIQUE NOT NULL,
    name       VARCHAR(100)  NOT NULL,
    phone      VARCHAR(20),
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id  INT           NOT NULL,
    road_name   VARCHAR(100)  NOT NULL,
    category    ENUM('accident','broken_vehicle','fallen_tree','flood','road_obstacle','traffic_light_damage') NOT NULL,
    description TEXT          NOT NULL,
    status      ENUM('pending','process','completed') DEFAULT 'pending',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id  INT           NOT NULL,
    title       VARCHAR(100)  NOT NULL,
    message     TEXT          NOT NULL,
    is_read     BOOLEAN       DEFAULT FALSE,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS traffic_data (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    road_name        VARCHAR(100)  NOT NULL DEFAULT 'Jalan MT Haryono',
    vehicle_count    INT           NOT NULL,
    average_speed    DECIMAL(5,2)  NOT NULL,
    congestion_level ENUM('Normal','Padat','Macet','Sangat Macet') NOT NULL,
    observation_time TIMESTAMP     NOT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_traffic_road_name       (road_name),
    INDEX idx_traffic_observation_time (observation_time)
);

CREATE TABLE IF NOT EXISTS incidents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    road_name     VARCHAR(100)  NOT NULL,
    incident_type ENUM('accident','broken_vehicle','fallen_tree','flood','road_obstacle','traffic_light_damage') NOT NULL,
    description   TEXT          NOT NULL,
    status        ENUM('active','resolved') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_road_name (road_name),
    INDEX idx_status    (status)
);

CREATE TABLE IF NOT EXISTS zones (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50)   NOT NULL,
    district   VARCHAR(100)  NOT NULL,
    area_km2   DECIMAL(8,2)  DEFAULT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO zones (id, name, district, area_km2) VALUES
    (1, 'A', 'Jakarta Pusat',   48.13),
    (2, 'B', 'Jakarta Selatan', 141.27),
    (3, 'C', 'Jakarta Utara',   146.66),
    (4, 'D', 'Jakarta Barat',   129.54),
    (5, 'E', 'Jakarta Timur',   187.73);

CREATE TABLE IF NOT EXISTS environment_data (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id    VARCHAR(50)   NOT NULL DEFAULT 'UNKNOWN',

    -- Data dari Rain Sensor (potentiometer → ADC D34)
    rainfall     DECIMAL(7,2)  NOT NULL DEFAULT 0
                 COMMENT 'Intensitas hujan mm/h dari rain sensor potentiometer',

    -- Data dari HC-SR04 Ultrasonic
    water_level  DECIMAL(7,2)  NOT NULL DEFAULT 0
                 COMMENT 'Ketinggian air cm dari ultrasonic HC-SR04',

    -- Dihitung otomatis oleh php-environment berdasarkan water_level
    flood_status VARCHAR(20)   DEFAULT NULL
                 COMMENT 'Aman/Waspada/Siaga/Bahaya',

    recorded_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sensor_id  (sensor_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB COMMENT='Data sensor lingkungan: rainfall + water_level dari ESP32';

CREATE TABLE IF NOT EXISTS environment_alerts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type  ENUM('FLOOD_HIGH','RAIN_HEAVY') NOT NULL
                COMMENT 'Hanya 2 tipe sesuai sensor yang ada',
    value       VARCHAR(50)   NOT NULL COMMENT 'Nilai yang memicu alert',
    threshold   VARCHAR(50)   NOT NULL COMMENT 'Nilai ambang batas',
    message     TEXT          NOT NULL,
    severity    ENUM('WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
    status      ENUM('active','resolved')  NOT NULL DEFAULT 'active',
    resolved_at TIMESTAMP     NULL DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_status     (status),
    INDEX idx_alert_type (alert_type)
) ENGINE=InnoDB COMMENT='Alert otomatis dari sensor rainfall dan water_level';

CREATE TABLE IF NOT EXISTS ml_predictions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    model_type       VARCHAR(50)   NOT NULL DEFAULT 'congestion',
    zone             VARCHAR(100)  DEFAULT 'MT_Haryono',
    input_data       JSON          NOT NULL,
    result           JSON          NOT NULL,
    confidence_score DECIMAL(5,4)  DEFAULT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_model_type (model_type),
    INDEX idx_created_at (created_at),
    INDEX idx_zone       (zone)
);