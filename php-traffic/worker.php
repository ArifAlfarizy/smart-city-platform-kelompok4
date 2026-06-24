<?php
// php-traffic/worker.php

// 1. Muat autoloader Composer dan kelas database inti
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/RabbitMQConsumer.php';

// 2. Fungsi pembantu untuk memuat variabel environment dari file .env secara manual
function loadLocalEnv($dir) {
    if (!file_exists($dir . '/.env')) {
        echo " [X] Error: .env file not found at $dir\n";
        exit(1);
    }
    
    $lines = file($dir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Abaikan baris komentar
        if (strpos(trim($line), '#') === 0) continue;
        
        // Pecah berdasarkan tanda sama dengan (=)
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

// 3. Jalankan pemuatan .env agar $_ENV['RABBITMQ_HOST'] dll terisi dengan benar
loadLocalEnv(__DIR__);

echo " [*] Initializing Traffic Service Worker...\n";

try {
    // 4. Instansiasi objek Consumer dan mulai mendengarkan antrean
    $consumer = new RabbitMQConsumer();
    $consumer->listen();
    
} catch (\Exception $e) {
    echo " [X] Critical Worker Error: " . $e->getMessage() . "\n";
    exit(1);
}