<?php
// public/index.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function loadEnv($dir) {
    if (!file_exists($dir . '/.env')) return;
    $lines = file($dir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

loadEnv(dirname(__DIR__));

require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/Models/TrafficData.php';
require_once dirname(__DIR__) . '/app/Controllers/TrafficController.php';
require_once dirname(__DIR__) . '/app/Models/Incident.php';
require_once dirname(__DIR__) . '/app/Controllers/IncidentController.php';

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Standard Response JSON sesuai aturan PRD
function jsonResponse($status, $code, $message, $data = null) {
    http_response_code($code);
    echo json_encode([
        "status" => $status,
        "code" => $code,
        "message" => $message,
        "timestamp" => date(DATE_ISO8601),
        "service" => "php-traffic"
    ]);
    exit();
}

// Inisialisasi Controller
$incidentController = new IncidentController();
$trafficController = new TrafficController();

// ==================== DAFTAR PERUTEAN (ROUTING) API ====================

// Rute Kesihatan: GET /api/traffic/health
if ($requestUri === '/api/traffic/health' && $requestMethod === 'GET') {
    $db = Database::getInstance()->getConnection();
    jsonResponse("success", 200, "Traffic Service is healthy and Database is connected successfully!");
} 

// Rute 1: POST /traffic-data atau /api/traffic-data (Menerima data dari IoT Gateway / Simulator)
elseif (($requestUri === '/traffic-data' || $requestUri === '/api/traffic-data') && $requestMethod === 'POST') {
    $trafficController->handlePostTrafficData();
} 

// Rute 2: GET /traffic-status atau /api/traffic-status (Mengambil kepadatan lalu lintas terbaru untuk Dashboard)
elseif (($requestUri === '/traffic-status' || $requestUri === '/api/traffic-status') && $requestMethod === 'GET') {
    $trafficController->handleGetTrafficStatus();
} 

// Rute 3: GET /traffic-history atau /api/traffic-history (Melihat log riwayat sensor)
elseif (($requestUri === '/traffic-history' || $requestUri === '/api/traffic-history') && $requestMethod === 'GET') {
    $trafficController->handleGetTrafficHistory();
} 

// Rute 4: GET /traffic-summary atau /api/traffic-summary (Menampilkan ringkasan volume lalu lintas)
elseif (($requestUri === '/traffic-summary' || $requestUri === '/api/traffic-summary') && $requestMethod === 'GET') {
    $trafficController->handleGetTrafficSummary();
} 

// Rute 5: POST /api/traffic/incidents (Operator mencatat insiden baru)
elseif ($requestUri === '/api/traffic/incidents' && $requestMethod === 'POST') {
    $incidentController->handleCreateIncident();
} 

// Rute 6: GET /api/traffic/incidents (Melihat daftar semua insiden)
elseif ($requestUri === '/api/traffic/incidents' && $requestMethod === 'GET') {
    $incidentController->handleGetAllIncidents();
} 

// Rute 7: PUT /api/traffic/incidents/{id} (Operator mengubah status ke resolved)
elseif (preg_match('/^\/api\/traffic\/incidents\/([0-9]+)$/', $requestUri, $matches) && $requestMethod === 'PUT') {
    $incidentId = $matches[1];
    $incidentController->handleResolveIncident($incidentId);
}

// Rute Default (404)
else {
    jsonResponse("error", 404, "Endpoint not found in php-traffic service");
}
