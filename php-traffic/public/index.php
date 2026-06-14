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

// 4. Muat berkas-berkas Model (Autoload manual sesuai arsitektur folder PRD) [cite: 128]
require_once dirname(__DIR__) . '/app/Models/TrafficData.php';
// Kita siapkan load file Incident.php di bawah ini untuk langkah selanjutnya
if (file_exists(dirname(__DIR__) . '/app/Models/Incident.php')) {
    require_once dirname(__DIR__) . '/app/Models/Incident.php';
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Standard Response JSON sesuai aturan PRD [cite: 355]
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

// 5. Logika Routing
if ($requestUri === '/api/traffic/health' && $requestMethod === 'GET') {
    $db = Database::getInstance()->getConnection();
    jsonResponse("success", 200, "Traffic Service is healthy and Database is connected successfully!");
} else {
    jsonResponse("error", 404, "Endpoint not found");
}