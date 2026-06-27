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
            INSERT INTO environment_data
                (sensor_id, rainfall, water_level, flood_status, recorded_at)
            VALUES
                (:sensor_id, :rainfall, :water_level, :flood_status, :recorded_at)
        ');

        $stmt->execute([
            ':sensor_id'    => $data['sensor_id']    ?? 'UNKNOWN',
            ':rainfall'     => $data['rainfall'],
            ':water_level'  => $data['water_level'],
            ':flood_status' => $data['flood_status']  ?? 'Aman',
            ':recorded_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->findById((int)$this->db->lastInsertId());
    }

    public function latest(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM environment_data ORDER BY recorded_at DESC LIMIT 1'
        );
        return $stmt->fetch() ?: null;
    }

    public function history(
        ?string $from  = null,
        ?string $to    = null,
        int     $limit = 50
    ): array {
        $sql    = 'SELECT * FROM environment_data WHERE 1=1';
        $params = [];

        if ($from) {
            $sql             .= ' AND recorded_at >= :from';
            $params[':from']  = $from . ' 00:00:00';
        }
        if ($to) {
            $sql           .= ' AND recorded_at <= :to';
            $params[':to']  = $to . ' 23:59:59';
        }

        $sql .= ' ORDER BY recorded_at DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function createAlert(array $data): ?array
    {
        $check = $this->db->prepare(
            "SELECT id FROM environment_alerts
             WHERE alert_type = :alert_type AND status = 'active'
             LIMIT 1"
        );
        $check->execute([':alert_type' => $data['alert_type']]);

        if ($check->fetch()) {
            return null;
        }

        $stmt = $this->db->prepare('
            INSERT INTO environment_alerts
                (alert_type, value, threshold, message, severity, status)
            VALUES
                (:alert_type, :value, :threshold, :message, :severity, \'active\')
        ');

        $stmt->execute([
            ':alert_type' => $data['alert_type'],
            ':value'      => $data['value'],
            ':threshold'  => $data['threshold'],
            ':message'    => $data['message'],
            ':severity'   => $data['severity'] ?? 'WARNING',
        ]);

        return $this->getAlertById((int)$this->db->lastInsertId());
    }

    public function getAlerts(string $status = 'active'): array
    {
        if ($status === 'all') {
            $stmt = $this->db->query(
                'SELECT * FROM environment_alerts ORDER BY created_at DESC'
            );
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM environment_alerts WHERE status = :status ORDER BY created_at DESC"
            );
            $stmt->execute([':status' => $status]);
        }

        return $stmt->fetchAll() ?: [];
    }

    public function getAlertById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM environment_alerts WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function resolveAlert(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE environment_alerts
             SET status = 'resolved', resolved_at = NOW()
             WHERE id = :id AND status = 'active'"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function findById(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM environment_data WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}