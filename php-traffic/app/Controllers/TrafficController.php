<?php
// app/Controllers/TrafficController.php

require_once dirname(__DIR__) . '/Models/TrafficData.php';

class TrafficController {
    private $trafficModel;

    public function __construct() {
        $this->trafficModel = new TrafficData();
    }

    /**
     * Menangani POST /api/traffic/sensor
     * Menerima kiriman data dari IoT Gateway (Node-RED)
     */
    public function handleSensorInput() {
        // Ambil input JSON dari request body
        $inputData = json_decode(file_get_contents("php://input"), true);

        // Validasi input dasar
        if (!isset($inputData['sensor_id']) || !isset($inputData['zone']) || 
            !isset($inputData['vehicle_count']) || !isset($inputData['avg_speed']) || 
            !isset($inputData['congestion_level'])) {
            
            $this->sendResponse("error", 400, "Incomplete sensor data payload");
        }

        $sensor_id = $inputData['sensor_id'];
        $zone = strtoupper($inputData['zone']);
        $vehicle_count = intval($inputData['vehicle_count']);
        $avg_speed = floatval($inputData['avg_speed']);
        $congestion_level = intval($inputData['congestion_level']);

        // Validasi enum zona sesuai aturan DB ENUM('A', 'B', 'C')
        if (!in_array($zone, ['A', 'B', 'C'])) {
            $this->sendResponse("error", 400, "Invalid zone. Allowed zones are A, B, or C");
        }

        // Simpan ke database via Model
        $insertedId = $this->trafficModel->create($sensor_id, $zone, $vehicle_count, $avg_speed, $congestion_level);

        if ($insertedId) {
            // [TODO SPRINT BERIKUTNYA]: Publish event 'traffic.sensor.received' ke RabbitMQ disini
            
            $this->sendResponse("success", 201, "Sensor data recorded successfully", [
                "id" => $insertedId,
                "zone" => $zone
            ]);
        } else {
            $this->sendResponse("error", 500, "Failed to record sensor data to database");
        }
    }

    /**
     * Menangani GET /api/traffic/current
     * Melihat kondisi lalu lintas terbaru dari setiap zona
     */
    public function handleGetCurrentStatus() {
        $currentStatus = $this->trafficModel->getCurrentStatus();
        $this->sendResponse("success", 200, "Successfully retrieved current traffic status", $currentStatus);
    }

    /**
     * Menangani GET /api/traffic/zones/{zone}
     * Melihat riwayat data lalu lintas berdasarkan parameter zona
     */
    public function handleGetZoneHistory($zone) {
        $zone = strtoupper($zone);
        if (!in_array($zone, ['A', 'B', 'C'])) {
            $this->sendResponse("error", 400, "Invalid zone parameter. Allowed zones are A, B, or C");
        }

        $history = $this->trafficModel->getHistoryByZone($zone);
        $this->sendResponse("success", 200, "Successfully retrieved traffic history for zone " . $zone, $history);
    }

    /**
     * Helper internal untuk standardisasi format JSON response PRD
     */
    private function sendResponse($status, $code, $message, $data = null) {
        http_response_code($code);
        echo json_encode([
            "status" => $status,
            "code" => $code,
            "data" => $data,
            "message" => $message,
            "timestamp" => date(DATE_ISO8601),
            "service" => "php-traffic"
        ]);
        exit();
    }
}