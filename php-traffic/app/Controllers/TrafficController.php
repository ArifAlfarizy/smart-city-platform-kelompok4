<?php
// app/Controllers/TrafficController.php

require_once dirname(__DIR__) . '/Models/TrafficData.php';
require_once dirname(__DIR__) . '/Services/RabbitMQPublisher.php';

class TrafficController {
    private $trafficModel;

    public function __construct() {
        $this->trafficModel = new TrafficData();
    }

    /**
     * Menangani POST /traffic-data
     */
    public function handlePostTrafficData() {
        $inputData = json_decode(file_get_contents("php://input"), true);

        // Validasi input data sesuai spesifikasi baru
        if (!isset($inputData['vehicle_count']) || !isset($inputData['average_speed']) || 
            !isset($inputData['congestion_level']) || !isset($inputData['observation_time'])) {
            
            $this->sendResponse("error", 400, "Incomplete traffic data payload");
        }

        $road_name = $inputData['road_name'] ?? 'Jalan MT Haryono'; 
        $vehicle_count = intval($inputData['vehicle_count']);
        $average_speed = floatval($inputData['average_speed']);
        $congestion_level = $inputData['congestion_level']; // Normal, Padat, Macet, Sangat Macet
        $observation_time = $inputData['observation_time'];

        // Tambahan Validasi: Standarisasi Road Name
        $allowedRoads = [
            "Gatot Subroto", "Jalan MT Haryono", "Jalan Raya Pasar Minggu", 
            "Jalan Raya Kalibata", "Jalan Prof Dr Soepomo", "Jalan KH Abdullah Syafei", 
            "Manggarai", "Cawang"
        ];

        if (!in_array($road_name, $allowedRoads)) {
            $this->sendResponse("error", 400, "Invalid road name. Use standardized road names like 'Jalan MT Haryono'");
        }

        // 1. Simpan ke database lokal
        $insertedId = $this->trafficModel->create($road_name, $vehicle_count, $average_speed, $congestion_level, $observation_time);

        if ($insertedId) {
            
            // 2. Kirim Event ke RabbitMQ dengan routing key 'traffic.updated'
            $publisher = new RabbitMQPublisher();

            // Disesuaikan agar bersih mengikuti spesifikasi payload minimal ML Consumer di Skema (1).pdf
            $rabbitPayload = [
                "road_name"        => $road_name,
                "vehicle_count"    => (int)$vehicle_count,
                "average_speed"    => $average_speed,
                "observation_time" => $observation_time
            ];

            // Mempublikasikan event 'traffic.updated'
            $publisher->publishEvent('traffic.updated', $rabbitPayload);
            
            // 3. Beri respons sukses ke client / simulator IoT
            $localResponse = [
                "id"               => (int)$insertedId,
                "road_name"        => $road_name,
                "vehicle_count"    => (int)$vehicle_count,
                "average_speed"    => $average_speed,
                "congestion_level" => $congestion_level,
                "observation_time" => $observation_time
            ];

            $this->sendResponse("success", 201, "Traffic data recorded and event published successfully", $localResponse);
        } else {
            $this->sendResponse("error", 500, "Failed to record traffic data to database");
        }
    }

    /**
     * Menangani GET /traffic-status
     */
    public function handleGetTrafficStatus() {
        $currentStatus = $this->trafficModel->getLatestStatus();
        $this->sendResponse("success", 200, "Successfully retrieved latest traffic status", $currentStatus);
    }

    /**
     * Menangani GET /traffic-history
     */
    public function handleGetTrafficHistory() {
        $history = $this->trafficModel->getHistory();
        $this->sendResponse("success", 200, "Successfully retrieved traffic logs history", $history);
    }

    /**
     * Menangani GET /traffic-summary
     */
    public function handleGetTrafficSummary() {
        $summary = $this->trafficModel->getSummary();
        $this->sendResponse("success", 200, "Successfully retrieved traffic data summary", $summary);
    }

    /**
     * Helper internal untuk standardisasi format JSON response
     */
    private function sendResponse($status, $code, $message, $data = null) {
        http_response_code($code);
        header('Content-Type: application/json');
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