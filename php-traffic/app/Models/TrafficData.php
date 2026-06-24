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
     * Menyimpan data sensor lalu lintas baru ke database (Spesifikasi Baru)
     */
    public function create($road_name, $vehicle_count, $avg_speed, $congestion_level, $observation_time) {
        $query = "INSERT INTO " . $this->table . " 
                  (road_name, vehicle_count, average_speed, congestion_level, observation_time) 
                  VALUES (:road_name, :vehicle_count, :average_speed, :congestion_level, :observation_time)";
        
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':road_name', $road_name);
        $stmt->bindParam(':vehicle_count', $vehicle_count, PDO::PARAM_INT);
        $stmt->bindParam(':average_speed', $avg_speed);
        $stmt->bindParam(':congestion_level', $congestion_level); // String: Normal, Padat, Macet, Sangat Macet
        $stmt->bindParam(':observation_time', $observation_time);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Mengambil data kondisi lalu lintas paling terbaru untuk dashboard (GET /traffic-status)
     */
    public function getLatestStatus() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY observation_time DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil seluruh riwayat log sensor data lalu lintas (GET /traffic-history)
     */
    public function getHistory() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY observation_time DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menyajikan ringkasan data lalu lintas harian (GET /traffic-summary)
     */
    public function getSummary() {
        $query = "SELECT road_name, DATE(observation_time) as date, 
                         SUM(vehicle_count) as total_vehicles, 
                         AVG(average_speed) as avg_speed_all 
                  FROM " . $this->table . " 
                  GROUP BY road_name, DATE(observation_time)
                  ORDER BY date DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}