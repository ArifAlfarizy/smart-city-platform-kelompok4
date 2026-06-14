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

// 2. JALANKAN loadEnv SEKARANG agar $_ENV terisi data dari file .env
loadEnv(dirname(__DIR__));

// 3. BARU MUAT file Database.php setelah $_ENV dipastikan sudah siap dan berisi data
require_once dirname(__DIR__) . '/app/Database.php';


$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

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

// 4. Logika Routing
if ($requestUri === '/api/traffic/health' && $requestMethod === 'GET') {
    $db = Database::getInstance()->getConnection();
    jsonResponse("success", 200, "Traffic Service is healthy and Database is connected successfully!");
} else {
    jsonResponse("error", 404, "Endpoint not found");
}