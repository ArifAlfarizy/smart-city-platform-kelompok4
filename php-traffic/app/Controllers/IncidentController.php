<?php
// app/Controllers/IncidentController.php

require_once dirname(__DIR__) . '/Models/Incident.php';

class IncidentController {
    private $incidentModel;

    public function __construct() {
        $this->incidentModel = new Incident();
    }

    /**
     * Menangani POST /api/traffic/incidents (Operator Only)
     */
    public function handleCreateIncident() {
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