<?php
// app/Models/SensorReading.php
namespace App\Models;

class SensorReading
{
    private \PDO $db;

    public const AQI_THRESHOLD   = 100;
    public const FLOOD_THRESHOLD = 50;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): array
    {
        $sql = '
            INSERT INTO environment_data
                (sensor_id, zone, aqi, temperature, humidity, flood_level,
                 pm25, pm10, no2, co, o3, recorded_at)
            VALUES
                (:sensor_id, :zone, :aqi, :temperature, :humidity, :flood_level,
                 :pm25, :pm10, :no2, :co, :o3, :recorded_at)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sensor_id'   => $data['sensor_id']   ?? 'UNKNOWN',
            ':zone'        => $data['zone'],
            ':aqi'         => $data['aqi'],
            ':temperature' => $data['temperature'],
            ':humidity'    => $data['humidity'],
            ':flood_level' => $data['flood_level']  ?? 0,
            ':pm25'        => $data['pm25']          ?? null,
            ':pm10'        => $data['pm10']          ?? null,
            ':no2'         => $data['no2']           ?? null,
            ':co'          => $data['co']            ?? null,
            ':o3'          => $data['o3']            ?? null,
            ':recorded_at' => $data['recorded_at']  ?? date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->lastInsertId();

        $alertModel = new Alert();

        if ((float)$data['aqi'] > self::AQI_THRESHOLD) {
            $severity = (float)$data['aqi'] > 150 ? 'CRITICAL' : 'WARNING';
            $alertModel->createIfNotExists([
                'zone'       => $data['zone'],
                'alert_type' => 'AQI_HIGH',
                'value'      => (string)$data['aqi'],
                'threshold'  => (string)self::AQI_THRESHOLD,
                'message'    => "AQI mencapai {$data['aqi']} di Zona {$data['zone']}. "
                              . "Batas aman terlampaui. Warga disarankan menggunakan masker.",
                'severity'   => $severity,
            ]);
        }

        if ((float)($data['flood_level'] ?? 0) > self::FLOOD_THRESHOLD) {
            $alertModel->createIfNotExists([
                'zone'       => $data['zone'],
                'alert_type' => 'FLOOD_HIGH',
                'value'      => (string)$data['flood_level'],
                'threshold'  => (string)self::FLOOD_THRESHOLD,
                'message'    => "Ketinggian air mencapai {$data['flood_level']} cm di Zona {$data['zone']}. "
                              . "Potensi banjir. Warga di dataran rendah agar waspada.",
                'severity'   => 'CRITICAL',
            ]);
        }

        return $this->findById($id);
    }

    public function latestPerZone(?string $zone = null): array
    {
        $where = $zone ? 'WHERE ed.zone = :zone' : '';

        $sql = "
            SELECT ed.*
            FROM environment_data ed
            INNER JOIN (
                SELECT zone, MAX(recorded_at) AS max_rec
                FROM environment_data
                GROUP BY zone
            ) latest ON ed.zone = latest.zone AND ed.recorded_at = latest.max_rec
            {$where}
            ORDER BY ed.zone
        ";

        $stmt = $this->db->prepare($sql);
        if ($zone) {
            $stmt->bindValue(':zone', $zone);
        }
        $stmt->execute();

        return $zone ? ($stmt->fetch() ?: []) : $stmt->fetchAll();
    }

    public function history(string $zone, ?string $from = null, ?string $to = null, int $limit = 100): array
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
        $sql   = "SELECT * FROM environment_data {$where} ORDER BY recorded_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function findById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM environment_data WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }
}
