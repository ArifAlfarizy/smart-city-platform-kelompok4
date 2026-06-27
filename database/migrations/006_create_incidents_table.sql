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