<?php
// app/Models/Database.php
namespace App\Models;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host   = getenv('DB_HOST')     ?: 'mysql';
            $port   = getenv('DB_PORT')     ?: '3306';
            $dbname = getenv('DB_NAME')     ?: 'smartcity';
            $user   = getenv('DB_USER')     ?: 'root';
            $pass   = getenv('DB_PASSWORD') ?: 'rootpass';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(503);
                echo json_encode([
                    'status'    => 'error',
                    'code'      => 503,
                    'message'   => 'Database connection failed',
                    'timestamp' => date('c'),
                    'service'   => 'environment-service',
                ]);
                exit;
            }
        }

        return self::$instance;
    }

    public static function isAlive(): bool
    {
        try {
            self::getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
