<?php
// app/Models/TrafficData.php

require_once dirname(__DIR__) . '/Database.php';

class TrafficData {
    private $db;
    private $table = "traffic_data";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Menyimpan data sensor lalu lintas baru ke database
     */
    public function create($sensor_id, $zone, $vehicle_count, $avg_speed, $congestion_level) {
        $query = "INSERT INTO " . $this->table . " 
                  (sensor_id, zone, vehicle_count, avg_speed, congestion_level, recorded_at) 
                  VALUES (:sensor_id, :zone, :vehicle_count, :avg_speed, :congestion_level, NOW())";
        
        $stmt = $this->db->prepare($query);

        // Bind parameters untuk mencegah SQL Injection
        $stmt->bindParam(':sensor_id', $sensor_id);
        $stmt->bindParam(':zone', $zone);
        $stmt->bindParam(':vehicle_count', $vehicle_count, PDO::PARAM_INT);
        $stmt->bindParam(':avg_speed', $avg_speed);
        $stmt->bindParam(':congestion_level', $congestion_level, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Mengambil data kondisi lalu lintas paling terbaru untuk semua zona atau per zona
     * Digunakan untuk endpoint: GET /api/traffic/current
     */
    public function getCurrentStatus() {
        // Query untuk mengambil baris terakhir dari masing-masing zona (A, B, C)
        $query = "SELECT t1.* FROM " . $this->table . " t1
                  INNER JOIN (
                      SELECT zone, MAX(recorded_at) as max_recorded 
                      FROM " . $this->table . " 
                      GROUP BY zone
                  ) t2 ON t1.zone = t2.zone AND t1.recorded_at = t2.max_recorded
                  ORDER BY t1.zone ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mengambil riwayat data traffic berdasarkan zona tertentu
     * Digunakan untuk endpoint: GET /api/traffic/zones/{zone}
     */
    public function getHistoryByZone($zone, $limit = 50) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE zone = :zone 
                  ORDER BY recorded_at DESC 
                  LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':zone', $zone);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}