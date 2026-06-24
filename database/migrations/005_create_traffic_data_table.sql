-- 005_create_traffic_data_table
CREATE TABLE IF NOT EXISTS traffic_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  road_name VARCHAR(100) NOT NULL DEFAULT 'Jalan MT Haryono',
  vehicle_count INT NOT NULL,
  average_speed DECIMAL(5,2) NOT NULL,
  congestion_level ENUM('Normal', 'Padat', 'Macet', 'Sangat Macet') NOT NULL,
  observation_time TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexing untuk optimasi filter query dashboard sesuai instruksi PRD
  INDEX idx_traffic_road_name (road_name),
  INDEX idx_traffic_observation_time (observation_time)
);