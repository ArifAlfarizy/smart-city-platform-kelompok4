<?php
// app/Controllers/IncidentController.php

require_once dirname(__DIR__) . '/Models/Incident.php';

class IncidentController {
    private $incidentModel;

    public function __construct() {
        $this->incidentModel = new Incident();
    }

    /**
     * Helper internal untuk memvalidasi apakah aktor memiliki klaim role 'operator' di JWT
     */
    private function validateOperatorAccess() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            $this->sendResponse("error", 401, "Access denied. Token missing from Authorization header");
        }

        $parts = explode(" ", $authHeader);
        $token = $parts[1] ?? null;

        if (!$token) {
            $this->sendResponse("error", 401, "Access denied. Invalid Authorization bearer format");
        }

        $tokenParts = explode('.', $token);
        $payloadB64 = $tokenParts[1] ?? null;

        if (!$payloadB64) {
            $this->sendResponse("error", 400, "Malformed JWT structure");
        }

        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64));
        $payload = json_decode($payloadJson, true);

        if (!isset($payload['role']) || $payload['role'] !== 'operator') {
            $this->sendResponse("error", 403, "Access denied. Operator role is required to perform this action");
        }

        return $payload;
    }

    /**
     * Menangani POST /api/traffic/incidents (Operator Only)
     */
    public function handleCreateIncident() {
        // Validasi hak akses Operator terdepan
        $this->validateOperatorAccess();

        $inputData = json_decode(file_get_contents("php://input"), true);

        // Validasi input parameter baru (road_name menggantikan zone)
        if (!isset($inputData['road_name']) || !isset($inputData['incident_type']) || !isset($inputData['description'])) {
            $this->sendResponse("error", 400, "Incomplete incident payload");
        }

        $road_name = $inputData['road_name'];
        $incident_type = $inputData['incident_type'];
        $description = $inputData['description'];

        // Daftar Standarisasi Road Name berdasarkan Dokumen Lampiran Halaman 1
        $allowedRoads = [
            "Gatot Subroto", "Jalan MT Haryono", "Jalan Raya Pasar Minggu", 
            "Jalan Raya Kalibata", "Jalan Prof Dr Soepomo", "Jalan KH Abdullah Syafei", 
            "Manggarai", "Cawang"
        ];

        if (!in_array($road_name, $allowedRoads)) {
            $this->sendResponse("error", 400, "Invalid road name. Use standardized road names like 'Jalan MT Haryono'");
        }

        // Daftar Standarisasi Tipe Insiden berdasarkan Dokumen Lampiran Halaman 1
        $allowedTypes = [
            "accident", "broken_vehicle", "fallen_tree", "flood", "road_obstacle", "traffic_light_damage"
        ];

        if (!in_array($incident_type, $allowedTypes)) {
            $this->sendResponse("error", 400, "Invalid incident type. Allowed: " . implode(', ', $allowedTypes));
        }

        $insertedId = $this->incidentModel->create($road_name, $incident_type, $description);

        if ($insertedId) {
            $this->sendResponse("success", 201, "Incident recorded successfully by Operator", [
                "id" => $insertedId,
                "road_name" => $road_name,
                "incident_type" => $incident_type,
                "status" => "active"
            ]);
        } else {
            $this->sendResponse("error", 500, "Failed to record incident to database");
        }
    }

    /**
     * Menangani GET /api/traffic/incidents
     */
    public function handleGetAllIncidents() {
        $incidents = $this->incidentModel->getAllIncidents();
        $this->sendResponse("success", 200, "Successfully retrieved all incidents", $incidents);
    }

    /**
     * Menangani PUT /api/traffic/incidents/{id} (Operator Only)
     */
    public function handleResolveIncident($id) {
        $this->validateOperatorAccess();

        $inputData = json_decode(file_get_contents("php://input"), true);

        if (!isset($inputData['status']) || $inputData['status'] !== 'resolved') {
            $this->sendResponse("error", 400, "Invalid or missing status. Status must be 'resolved'");
        }

        $updated = $this->incidentModel->updateStatus($id, 'resolved');

        if ($updated) {
            $this->sendResponse("success", 200, "Incident ID " . $id . " has been successfully resolved");
        } else {
            $this->sendResponse("error", 500, "Failed to update incident status. Make sure ID exists");
        }
    }

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