<?php
// app/Models/SensorReading.php
namespace App\Models;

class SensorReading
{
    private \PDO $db;

    public const AQI_THRESHOLD            = 100;
    public const FLOOD_THRESHOLD          = 50;
    public const RAIN_INTENSITY_THRESHOLD = 30;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): array
    {
        $sql = '
            INSERT INTO environment_data
                (sensor_id, zone,
                 aqi, aqi_status, pm25, pm10, no2, co, o3,
                 temperature, humidity,
                 rain_level, rain_intensity, rain_status,
                 flood_level, flood_status,
                 recorded_at)
            VALUES
                (:sensor_id, :zone,
                 :aqi, :aqi_status, :pm25, :pm10, :no2, :co, :o3,
                 :temperature, :humidity,
                 :rain_level, :rain_intensity, :rain_status,
                 :flood_level, :flood_status,
                 :recorded_at)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sensor_id'      => $data['sensor_id']      ?? 'UNKNOWN',
            ':zone'           => $data['zone'],
            // Udara
            ':aqi'            => $data['aqi'],
            ':aqi_status'     => $data['aqi_status']     ?? null,
            ':pm25'           => $data['pm25']            ?? null,
            ':pm10'           => $data['pm10']            ?? null,
            ':no2'            => $data['no2']             ?? null,
            ':co'             => $data['co']              ?? null,
            ':o3'             => $data['o3']              ?? null,
            // Cuaca
            ':temperature'    => $data['temperature'],
            ':humidity'       => $data['humidity'],
            // Hujan
            ':rain_level'     => $data['rain_level']     ?? 0,
            ':rain_intensity' => $data['rain_intensity'] ?? 0,
            ':rain_status'    => $data['rain_status']    ?? null,
            // Banjir
            ':flood_level'    => $data['flood_level']    ?? 0,
            ':flood_status'   => $data['flood_status']   ?? null,
            ':recorded_at'    => $data['recorded_at']    ?? date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->lastInsertId();

        $this->checkAndCreateAlerts($data);

        return $this->findById($id);
    }

    private function checkAndCreateAlerts(array $data): void
    {
        $alertModel = new Alert();
        $zone       = $data['zone'];

        // 1. AQI tinggi
        if ((float)$data['aqi'] > self::AQI_THRESHOLD) {
            $severity = (float)$data['aqi'] > 150 ? 'CRITICAL' : 'WARNING';
            $alertModel->createIfNotExists([
                'zone'       => $zone,
                'alert_type' => 'AQI_HIGH',
                'value'      => (string)$data['aqi'],
                'threshold'  => (string)self::AQI_THRESHOLD,
                'message'    => "AQI mencapai {$data['aqi']} ({$data['aqi_status']}) "
                              . "di Zona {$zone}. Batas aman terlampaui. "
                              . "Warga disarankan menggunakan masker.",
                'severity'   => $severity,
            ]);
        }

        // 2. Banjir
        if ((float)($data['flood_level'] ?? 0) > self::FLOOD_THRESHOLD) {
            $alertModel->createIfNotExists([
                'zone'       => $zone,
                'alert_type' => 'FLOOD_HIGH',
                'value'      => (string)$data['flood_level'],
                'threshold'  => (string)self::FLOOD_THRESHOLD,
                'message'    => "Ketinggian air mencapai {$data['flood_level']} cm "
                              . "(Status: {$data['flood_status']}) di Zona {$zone}. "
                              . "Warga di dataran rendah agar segera mengungsi.",
                'severity'   => 'CRITICAL',
            ]);
        }

        // 3. Hujan lebat (NEW — dari rain_intensity sensor)
        if ((float)($data['rain_intensity'] ?? 0) > self::RAIN_INTENSITY_THRESHOLD) {
            $severity = (float)$data['rain_intensity'] > 50 ? 'CRITICAL' : 'WARNING';
            $alertModel->createIfNotExists([
                'zone'       => $zone,
                'alert_type' => 'RAIN_HEAVY',
                'value'      => (string)$data['rain_intensity'],
                'threshold'  => (string)self::RAIN_INTENSITY_THRESHOLD,
                'message'    => "Intensitas hujan {$data['rain_intensity']} mm/jam "
                              . "({$data['rain_status']}) di Zona {$zone}. "
                              . "Waspadai potensi genangan dan banjir.",
                'severity'   => $severity,
            ]);
        }
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
