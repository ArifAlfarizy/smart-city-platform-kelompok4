<?php
// app/Services/RabbitMQConsumer.php

require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/app/Models/Incident.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConsumer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $incidentModel;

    public function __construct() {
        $this->host = $_ENV['RABBITMQ_HOST'] ?? 'localhost';
        $this->port = intval($_ENV['RABBITMQ_PORT'] ?? 5672);
        $this->user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $this->pass = $_ENV['RABBITMQ_PASSWORD'] ?? 'guest';
        $this->incidentModel = new Incident();
    }

    public function listen() {
        $exchange = 'city.events';
        $queueName = 'traffic.incident.queue';
        $routingKey = 'incident.created'; 

        $connection = new AMQPStreamConnection(
            $this->host, 
            $this->port, 
            $this->user, 
            $this->pass,
            '/',             // vhost default RabbitMQ
            false,           // insist
            'AMQPLAIN',      // login_method
            null,            // login_response
            'en_US',         // locale
            3.0,             // connection_timeout
            60.0,            // read_write_timeout
            null,            // context
            false,           // keepalive
            30               // HEARTBEAT
        );
        $channel = $connection->channel();

        // 1. Deklarasi exchange tipe topic sesuai kesepakatan arsitektur
        $channel->exchange_declare($exchange, 'topic', false, true, false);

        // 2. Deklarasi antrean khusus untuk didengar oleh Traffic Service
        $channel->queue_declare($queueName, false, true, false, false);
        $channel->queue_bind($queueName, $exchange, $routingKey);

        echo " [*] Traffic Service Worker waiting for citizen reports. To exit press CTRL+C\n";

        // 3. Callback saat ada pesan masuk dari RabbitMQ
        $callback = function ($msg) {
            echo ' [x] Received citizen report event: ', $msg->body, "\n";
            
            $data = json_decode($msg->body, true);

            if (isset($data['road_name']) && isset($data['category'])) {
                $road_name = $data['road_name'];
                $incident_type = $data['category'];
                $description = $data['description'] ?? 'Laporan otomatis dari aduan warga via Citizen Service';

                // Simpan otomatis ke tabel incidents
                $insertedId = $this->incidentModel->create($road_name, $incident_type, $description);
                
                if ($insertedId) {
                    echo " [-->] Successfully replicated citizen report into local incidents table. ID: $insertedId\n";
                    $msg->ack(); // Konfirmasi ke RabbitMQ bahwa pesan sukses diproses
                } else {
                    echo " [X] Failed to save replicated data to database\n";
                }
            } else {
                echo " [X] Invalid event payload structure\n";
                $msg->ack(); 
            }
        };

        $channel->basic_consume($queueName, '', false, false, false, false, $callback);

        // Loop terus-menerus selama worker menyala
        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}
