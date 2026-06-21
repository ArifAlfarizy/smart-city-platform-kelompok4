<?php
// app/Services/RabbitMQPublisher.php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $vhost;
    private string $exchange;

    public function __construct()
    {
        $this->host     = getenv('RABBITMQ_HOST')     ?: 'rabbitmq';
        $this->port     = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $this->user     = getenv('RABBITMQ_USER')     ?: 'guest';
        $this->pass     = getenv('RABBITMQ_PASSWORD') ?: 'guest';
        $this->vhost    = getenv('RABBITMQ_VHOST')    ?: '/';
        $this->exchange = 'city.events';
    }

    /**
     * Publish satu event ke exchange city.events
     *
     * @param string $routingKey  Contoh: 'environment.sensor.received'
     * @param array  $payload     Data yang akan dikirim sebagai JSON
     */
    public function publish(string $routingKey, array $payload): void
    {
        try {
            $connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->pass,
                $this->vhost
            );

            $channel = $connection->channel();

            // Deklarasi exchange (topic, durable — konsisten dengan consumer Python ML)
            $channel->exchange_declare(
                $this->exchange,
                'topic',
                false,   // passive
                true,    // durable
                false    // auto-delete
            );

            $body = json_encode(array_merge($payload, [
                '_event'     => $routingKey,
                '_source'    => 'environment-service',
                '_timestamp' => date('c'),
            ]), JSON_UNESCAPED_UNICODE);

            $msg = new AMQPMessage($body, [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $channel->basic_publish($msg, $this->exchange, $routingKey);

            $channel->close();
            $connection->close();

            error_log("[RabbitMQ] Published '{$routingKey}' → {$body}");

        } catch (\Exception $e) {
            // Jangan crash service hanya karena RabbitMQ down
            error_log("[RabbitMQ ERROR] {$e->getMessage()}");
        }
    }
}
