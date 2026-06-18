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

// 1. Definisikan fungsi loadEnv terlebih dahulu
function loadEnv($dir) {
    if (!file_exists($dir . '/.env')) return;
    $lines = file($dir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// 2. Jalankan loadEnv agar $_ENV terisi data dari file .env
loadEnv(dirname(__DIR__));

// 3. Muat file Core & Database core
require_once dirname(__DIR__) . '/app/Database.php';

// 4. Muat berkas-berkas Model (Autoload manual sesuai arsitektur folder PRD) 
require_once dirname(__DIR__) . '/app/Models/TrafficData.php';
if (file_exists(dirname(__DIR__) . '/app/Models/Incident.php')) {
    require_once dirname(__DIR__) . '/app/Models/Incident.php';
}

// 5. Muat berkas-berkas Controller 
if (file_exists(dirname(__DIR__) . '/app/Controllers/TrafficController.php')) {
    require_once dirname(__DIR__) . '/app/Controllers/TrafficController.php';
}
if (file_exists(dirname(__DIR__) . '/app/Controllers/IncidentController.php')) {
    require_once dirname(__DIR__) . '/app/Controllers/IncidentController.php';
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Standard Response JSON sesuai aturan PRD
function jsonResponse($status, $code, $message, $data = null) {
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

// Inisialisasi Controller jika berkasnya ada
$trafficController = class_exists('TrafficController') ? new TrafficController() : null;
$incidentController = class_exists('IncidentController') ? new IncidentController() : null;

// Rute 1: GET /api/traffic/health
if ($requestUri === '/api/traffic/health' && $requestMethod === 'GET') {
    $db = Database::getInstance()->getConnection();
    jsonResponse("success", 200, "Traffic Service is healthy and Database is connected successfully!");
} 

// Rute 2: POST /api/traffic/sensor
elseif ($requestUri === '/api/traffic/sensor' && $requestMethod === 'POST') {
    if ($trafficController) {
        $trafficController->handleSensorInput();
    } else {
        jsonResponse("error", 500, "TrafficController not implemented");
    }
} 

// Rute 3: GET /api/traffic/current
elseif ($requestUri === '/api/traffic/current' && $requestMethod === 'GET') {
    if ($trafficController) {
        $trafficController->handleGetCurrentStatus();
    } else {
        jsonResponse("error", 500, "TrafficController not implemented");
    }
} 

// Rute 4: GET /api/traffic/zones/{zone}
elseif (preg_match('/^\/api\/traffic\/zones\/([A-Za-z0-9]+)$/', $requestUri, $matches) && $requestMethod === 'GET') {
    if ($trafficController) {
        $zoneParam = $matches[1];
        $trafficController->handleGetZoneHistory($zoneParam);
    } else {
        jsonResponse("error", 500, "TrafficController not implemented");
    }
} 

// Rute 5: POST /api/traffic/incidents (Operator mencatat insiden baru)
elseif ($requestUri === '/api/traffic/incidents' && $requestMethod === 'POST') {
    if ($incidentController) {
        $incidentController->handleCreateIncident();
    } else {
        jsonResponse("error", 500, "IncidentController not implemented");
    }
} 

// Rute 6: GET /api/traffic/incidents (Melihat daftar semua insiden)
elseif ($requestUri === '/api/traffic/incidents' && $requestMethod === 'GET') {
    if ($incidentController) {
        $incidentController->handleGetAllIncidents();
    } else {
        jsonResponse("error", 500, "IncidentController not implemented");
    }
} 

// Rute 7: PUT /api/traffic/incidents/{id} (Operator menyelesaikan/menyembuhkan insiden)
elseif (preg_match('/^\/api\/traffic\/incidents\/([0-9]+)$/', $requestUri, $matches) && $requestMethod === 'PUT') {
    if ($incidentController) {
        $incidentId = $matches[1];
        $incidentController->handleResolveIncident($incidentId);
    } else {
        jsonResponse("error", 500, "IncidentController not implemented");
    }
} 

// Rute Default (404) 
else {
    jsonResponse("error", 404, "Endpoint not found");
}