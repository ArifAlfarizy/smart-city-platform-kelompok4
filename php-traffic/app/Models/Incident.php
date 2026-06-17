<?php
// app/Models/Incident.php

require_once dirname(__DIR__) . '/Database.php';

class Incident {
    private $db;
    private $table = "incidents";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Operator mencatat insiden baru manual
     * Digunakan untuk endpoint: POST /api/traffic/incidents
     */
    public function create($zone, $incident_type, $description) {
        $query = "INSERT INTO " . $this->table . " 
                  (zone, incident_type, description, status, created_at) 
                  VALUES (:zone, :incident_type, :description, 'active', NOW())";
        
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':zone', $zone);
        $stmt->bindParam(':incident_type', $incident_type);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Melihat semua daftar insiden lalu lintas
     * Digunakan untuk endpoint: GET /api/traffic/incidents
     */
    public function getAllIncidents() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Memperbarui status penanganan insiden (active -> resolved)
     * Digunakan untuk endpoint: PUT /api/traffic/incidents/{id}
     */
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}