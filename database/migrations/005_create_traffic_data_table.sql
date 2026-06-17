CREATE TABLE IF NOT EXISTS traffic_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sensor_id VARCHAR(50) NOT NULL,
  zone ENUM('A', 'B', 'C') NOT NULL,
  vehicle_count INT NOT NULL,
  avg_speed DECIMAL(5,2) NOT NULL,
  congestion_level INT NOT NULL, -- Skala 1-10
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexing untuk optimasi filter sesuai instruksi PRD
  INDEX idx_traffic_zone (zone),
  INDEX idx_traffic_recorded_at (recorded_at)
);