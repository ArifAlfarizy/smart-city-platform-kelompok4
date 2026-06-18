<?php
// app/Services/RabbitMQPublisher.php

// Muat autoloader Composer agar class dari php-amqplib bisa terbaca
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private $host;
    private $port;
    private $user;
    private $pass;

    public function __construct() {
        // Mengambil kredensial server RabbitMQ dari variabel global $_ENV
        $this->host = $_ENV['RABBITMQ_HOST'] ?? 'localhost';
        $this->port = intval($_ENV['RABBITMQ_PORT'] ?? 5672);
        $this->user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $this->pass = $_ENV['RABBITMQ_PASSWORD'] ?? 'guest';
    }

    /**
     * Mengirimkan data event ke Exchange RabbitMQ
     */
    public function publishEvent($routingKey, array $payload) {
        $exchange = 'city.events';

        try {
            // 1. Membuka koneksi stream ke broker RabbitMQ
            $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->pass);
            $channel = $connection->channel();

            // 2. Deklarasikan Exchange dengan tipe 'topic' sesuai arsitektur sistem kelompok
            $channel->exchange_declare($exchange, 'topic', false, true, false);

            // 3. Konversi payload array menjadi string JSON
            $msgBody = json_encode($payload);
            $msg = new AMQPMessage($msgBody, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT // Pesan tetap aman meskipun RabbitMQ restart
            ]);

            // 4. Publikasikan pesan ke exchange dengan routing key khusus
            $channel->basic_publish($msg, $exchange, $routingKey);

            // 5. Tutup channel dan koneksi secara rapi
            $channel->close();
            $connection->close();
            
            return true;
        } catch (\Exception $e) {
            // Jika RabbitMQ lokal mati atau gagal terhubung, log error-nya 
            // agar tidak mem-blocking/membuat crash proses simpan database utama
            error_log("RabbitMQ Publish Failed: " . $e->getMessage());
            return false;
        }
    }
}