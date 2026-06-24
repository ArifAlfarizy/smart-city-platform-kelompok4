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
        // 1. Ambil seluruh daftar header HTTP request masuk
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            $this->sendResponse("error", 401, "Access denied. Token missing from Authorization header");
        }

        // 2. Ekstrak string token dari format "Bearer <token>"
        $parts = explode(" ", $authHeader);
        $token = $parts[1] ?? null;

        if (!$token) {
            $this->sendResponse("error", 401, "Access denied. Invalid Authorization bearer format");
        }

        // 3. Pecah struktur struktur JWT (Header.Payload.Signature)
        $tokenParts = explode('.', $token);
        $payloadB64 = $tokenParts[1] ?? null;

        if (!$payloadB64) {
            $this->sendResponse("error", 400, "Malformed JWT structure");
        }

        // 4. Decode payload base64 ke format teks JSON asli
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $payloadB64));
        $payload = json_decode($payloadJson, true);

        // 5. Periksa hak akses aktor di dalam payload token sesuai aturan PRD
        if (!isset($payload['role']) || $payload['role'] !== 'operator') {
            $this->sendResponse("error", 403, "Access denied. Operator role is required to perform this action");
        }

        // Jika lolos, kembalikan data payload token (bisa digunakan untuk audit user_id nanti)
        return $payload;
    }

    /**
     * Menangani POST /api/traffic/incidents (Operator Only)
     */
    public function handleCreateIncident() {
        // Validasi hak akses Operator terdepan sebelum memproses data
        $this->validateOperatorAccess();

        $inputData = json_decode(file_get_contents("php://input"), true);

        // Validasi input
        if (!isset($inputData['zone']) || !isset($inputData['incident_type']) || !isset($inputData['description'])) {
            $this->sendResponse("error", 400, "Incomplete incident payload");
        }

        $zone = strtoupper($inputData['zone']);
        $incident_type = $inputData['incident_type'];
        $description = $inputData['description'];

        if (!in_array($zone, ['A', 'B', 'C'])) {
            $this->sendResponse("error", 400, "Invalid zone. Allowed zones are A, B, or C");
        }

        $insertedId = $this->incidentModel->create($zone, $incident_type, $description);

        if ($insertedId) {
            $this->sendResponse("success", 201, "Incident recorded successfully by Operator", [
                "id" => $insertedId,
                "zone" => $zone,
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
        // Validasi hak akses Operator terdepan sebelum memproses data
        $this->validateOperatorAccess();

        $inputData = json_decode(file_get_contents("php://input"), true);

        // Validasi status yang dikirim operator wajib 'resolved' sesuai PRD
        if (!isset($inputData['status']) || $inputData['status'] !== 'resolved') {
            $this->sendResponse("error", 400, "Invalid or missing status. Status must be 'resolved'");
        }

        // Jalankan update status di model
        $updated = $this->incidentModel->updateStatus($id, 'resolved');

        if ($updated) {
            $this->sendResponse("success", 200, "Incident ID " . $id . " has been successfully resolved");
        } else {
            $this->sendResponse("error", 500, "Failed to update incident status. Make sure ID exists");
        }
    }

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