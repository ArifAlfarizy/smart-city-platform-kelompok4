CREATE TABLE IF NOT EXISTS incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone ENUM('A', 'B', 'C') NOT NULL,
  incident_type VARCHAR(100) NOT NULL, -- Contoh: Kecelakaan, Kemacetan Total
  description TEXT NOT NULL,
  status ENUM('active', 'resolved') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexing untuk optimasi filter
  INDEX idx_incidents_zone (zone),
  INDEX idx_incidents_status (status)
);