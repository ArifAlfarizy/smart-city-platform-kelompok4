<?php
// app/Models/Alert.php
namespace App\Models;

class Alert
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createIfNotExists(array $data): ?array
    {
        $check = $this->db->prepare('
            SELECT id FROM environment_alerts
            WHERE zone = :zone AND alert_type = :alert_type AND status = \'active\'
            LIMIT 1
        ');
        $check->execute([
            ':zone'       => $data['zone'],
            ':alert_type' => $data['alert_type'],
        ]);

        if ($check->fetch()) {
            return null;
        }

        return $this->create($data);
    }

    public function create(array $data): array
    {
        $sql = '
            INSERT INTO environment_alerts
                (zone, alert_type, value, threshold, message, severity, status)
            VALUES
                (:zone, :alert_type, :value, :threshold, :message, :severity, \'active\')
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':zone'       => $data['zone'],
            ':alert_type' => $data['alert_type'],
            ':value'      => $data['value'],
            ':threshold'  => $data['threshold'],
            ':message'    => $data['message'],
            ':severity'   => $data['severity'] ?? 'WARNING',
        ]);

        return $this->findById((int)$this->db->lastInsertId());
    }

    public function getActive(?string $zone = null): array
    {
        $where  = "WHERE status = 'active'";
        $params = [];

        if ($zone) {
            $where   .= ' AND zone = :zone';
            $params[':zone'] = $zone;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM environment_alerts {$where} ORDER BY severity DESC, created_at DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getAll(?string $zone = null, int $limit = 50): array
    {
        $where  = '';
        $params = [];

        if ($zone) {
            $where           = 'WHERE zone = :zone';
            $params[':zone'] = $zone;
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM environment_alerts {$where} ORDER BY created_at DESC LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function resolve(int $id): bool
    {
        $stmt = $this->db->prepare('
            UPDATE environment_alerts
            SET status = \'resolved\', resolved_at = NOW()
            WHERE id = :id AND status = \'active\'
        ');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM environment_alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}
