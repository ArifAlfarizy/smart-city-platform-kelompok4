<?php
namespace App\Models;

class EnvironmentReading
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare('
            INSERT INTO environment_data (sensor_id, rainfall, water_level, flood_status, recorded_at)
            VALUES (:sensor_id, :rainfall, :water_level, :flood_status, :recorded_at)
        ');
        $stmt->execute([
            ':sensor_id'    => $data['sensor_id']   ?? 'UNKNOWN',
            ':rainfall'     => $data['rainfall'],
            ':water_level'  => $data['water_level'],
            ':flood_status' => $data['flood_status'] ?? 'Aman',
            ':recorded_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->findById((int)$this->db->lastInsertId());
    }

    public function latest(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM environment_data ORDER BY recorded_at DESC LIMIT 1');
        return $stmt->fetch() ?: null;
    }

    private function findById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM environment_data WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}