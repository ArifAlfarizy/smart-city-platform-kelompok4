<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $vhost;
    private string $exchange;

    public function __construct()
    {
        $this->host = getenv('RABBITMQ_HOST') ?: 'localhost';
        $this->port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
        $this->user = getenv('RABBITMQ_USER') ?: 'guest';
        $this->pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';
        $this->vhost = getenv('RABBITMQ_VHOST') ?: '/';
        $this->exchange = getenv('RABBITMQ_EXCHANGE') ?: 'city.events';
    }

    public function publish(string $routingKey, array $payload): bool
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

            $channel->exchange_declare(
                $this->exchange,
                'topic',
                false,
                true,
                false
            );

            $message = new AMQPMessage(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                [
                    'content_type'  => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            $channel->basic_publish($message, $this->exchange, $routingKey);

            $channel->close();
            $connection->close();

            return true;
        } catch (\Throwable $e) {
            log_message('error', '[RabbitMQ] Publish failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}