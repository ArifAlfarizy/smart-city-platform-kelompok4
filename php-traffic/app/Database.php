<?php
// app/Database.php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        // Mengambil data dari variabel $_ENV yang di-load di index.php
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbname = $_ENV['DB_NAME'] ?? 'smartcity';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->conn = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Jika koneksi gagal, kembalikan response error JSON
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "code" => 500,
                "data" => null,
                "message" => "Database connection failed: " . $e->getMessage(),
                "timestamp" => date(DATE_ISO8601),
                "service" => "php-traffic"
            ]);
            exit();
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}