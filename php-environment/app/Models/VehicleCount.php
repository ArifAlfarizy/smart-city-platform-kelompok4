<?php
// app/Models/VehicleCount.php
namespace App\Models;

class VehicleCount
{
    private \PDO $db;

    public const THRESHOLD_SEDANG = 10;
    public const THRESHOLD_PADAT  = 25;
    public const THRESHOLD_MACET  = 40;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): array
    {
        $trafficStatus = $data['traffic_status'] ?? $this->computeTrafficStatus(
            (int)$data['vehicle_count'],
            (float)($data['rain_intensity'] ?? 0)
        );

        $sql = '
            INSERT INTO vehicle_counts
                (sensor_id, zone, vehicle_count, interval_sec, traffic_status,
                 rain_intensity, rain_status, flood_level, recorded_at)
            VALUES
                (:sensor_id, :zone, :vehicle_count, :interval_sec, :traffic_status,
                 :rain_intensity, :rain_status, :flood_level, :recorded_at)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sensor_id'      => $data['sensor_id']      ?? 'UNKNOWN',
            ':zone'           => $data['zone'],
            ':vehicle_count'  => (int)$data['vehicle_count'],
            ':interval_sec'   => (int)($data['interval_sec']   ?? 60),
            ':traffic_status' => $trafficStatus,
            ':rain_intensity' => (float)($data['rain_intensity'] ?? 0),
            ':rain_status'    => $data['rain_status']    ?? null,
            ':flood_level'    => (float)($data['flood_level']   ?? 0),
            ':recorded_at'    => $data['recorded_at']    ?? date('Y-m-d H:i:s'),
        ]);

        return $this->findById((int)$this->db->lastInsertId());
    }

    public function computeTrafficStatus(int $count, float $rainIntensity): string
    {
        $factor = 1.0;
        if ($rainIntensity >= 50) {
            $factor = 0.5;
        } elseif ($rainIntensity >= 20) {
            $factor = 0.7;
        } elseif ($rainIntensity >= 5) {
            $factor = 0.85;
        }

        $sedang = (int)round(self::THRESHOLD_SEDANG * $factor);
        $padat  = (int)round(self::THRESHOLD_PADAT  * $factor);
        $macet  = (int)round(self::THRESHOLD_MACET  * $factor);

        if ($count >= $macet)  return 'Macet';
        if ($count >= $padat)  return 'Padat';
        if ($count >= $sedang) return 'Sedang';
        return 'Lancar';
    }

    public function latestPerZone(?string $zone = null): array
    {
        $where = $zone ? 'WHERE vc.zone = :zone' : '';

        $sql = "
            SELECT vc.*
            FROM vehicle_counts vc
            INNER JOIN (
                SELECT zone, MAX(recorded_at) AS max_rec
                FROM vehicle_counts
                GROUP BY zone
            ) latest ON vc.zone = latest.zone AND vc.recorded_at = latest.max_rec
            {$where}
            ORDER BY vc.zone
        ";

        $stmt = $this->db->prepare($sql);
        if ($zone) {
            $stmt->bindValue(':zone', $zone);
        }
        $stmt->execute();

        return $zone ? ($stmt->fetch() ?: []) : $stmt->fetchAll();
    }

    public function history(string $zone, ?string $from = null, ?string $to = null, int $limit = 200): array
    {
        $conditions = ['zone = :zone'];
        $params     = [':zone' => $zone];

        if ($from) {
            $conditions[] = 'recorded_at >= :from';
            $params[':from'] = $from;
        }
        if ($to) {
            $conditions[] = 'recorded_at <= :to';
            $params[':to'] = $to;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql   = "SELECT * FROM vehicle_counts {$where} ORDER BY recorded_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function aggregate(string $zone, string $period = '1h'): array
    {
        $intervalMap = [
            '1h'  => 'INTERVAL 1 HOUR',
            '6h'  => 'INTERVAL 6 HOUR',
            '24h' => 'INTERVAL 24 HOUR',
            '7d'  => 'INTERVAL 7 DAY',
        ];
        $interval = $intervalMap[$period] ?? 'INTERVAL 1 HOUR';

        $sql = "
            SELECT
                zone,
                COUNT(*)                                     AS total_records,
                SUM(vehicle_count)                           AS total_vehicles,
                ROUND(AVG(vehicle_count), 1)                 AS avg_vehicle_count,
                MAX(vehicle_count)                           AS peak_vehicle_count,
                ROUND(AVG(rain_intensity), 2)                AS avg_rain_intensity,
                MAX(rain_intensity)                          AS peak_rain_intensity,
                SUM(CASE WHEN traffic_status = 'Macet'  THEN 1 ELSE 0 END) AS macet_count,
                SUM(CASE WHEN traffic_status = 'Padat'  THEN 1 ELSE 0 END) AS padat_count,
                SUM(CASE WHEN traffic_status = 'Sedang' THEN 1 ELSE 0 END) AS sedang_count,
                SUM(CASE WHEN traffic_status = 'Lancar' THEN 1 ELSE 0 END) AS lancar_count,
                MIN(recorded_at)                             AS from_time,
                MAX(recorded_at)                             AS to_time
            FROM vehicle_counts
            WHERE zone = :zone
              AND recorded_at >= NOW() - {$interval}
            GROUP BY zone
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':zone', $zone);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    private function findById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vehicle_counts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}
