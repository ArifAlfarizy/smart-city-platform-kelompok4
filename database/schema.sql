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