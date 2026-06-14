<?php
// app/Controllers/SensorController.php
namespace App\Controllers;

use App\Models\SensorReading;
use App\Models\Database;
use App\Services\RabbitMQPublisher;
use App\Validators\SensorValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SensorController
{
    private SensorReading     $model;
    private RabbitMQPublisher $publisher;
    private SensorValidator   $validator;

    public function __construct()
    {
        $this->model     = new SensorReading();
        $this->publisher = new RabbitMQPublisher();
        $this->validator = new SensorValidator();
    }

    /**
     * POST /api/environment/sensor
     * Terima data sensor dari IoT Gateway (Node-RED → sini)
     */
    public function store(Request $request, Response $response): Response
    {
        $raw = (array)($request->getParsedBody() ?? []);

        try {
            $validated = $this->validator->validate($raw);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, [
                'status'    => 'error',
                'code'      => 422,
                'data'      => null,
                'message'   => $e->getMessage(),
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], 422);
        }

        $record = $this->model->create($validated);

        // Publish event ke RabbitMQ → konsumen ML Service (Tahap 5)
        $this->publisher->publish('environment.sensor.received', [
            'id'          => $record['id'],
            'sensor_id'   => $record['sensor_id'],
            'zone'        => $record['zone'],
            'aqi'         => (float)$record['aqi'],
            'temperature' => (float)$record['temperature'],
            'humidity'    => (float)$record['humidity'],
            'flood_level' => (float)$record['flood_level'],
            'pm25'        => $record['pm25'] !== null ? (float)$record['pm25'] : null,
            'pm10'        => $record['pm10'] !== null ? (float)$record['pm10'] : null,
            'timestamp'   => $record['recorded_at'],
        ]);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 201,
            'data'      => $record,
            'message'   => 'Data sensor lingkungan berhasil disimpan.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ], 201);
    }

    /**
     * GET /api/environment/current[?zone=A]
     * Kondisi lingkungan terkini per zona
     */
    public function current(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $zone   = isset($params['zone']) ? strtoupper($params['zone']) : null;

        if ($zone !== null && !in_array($zone, ['A','B','C','D','E'], true)) {
            return $this->json($response, [
                'status'    => 'error',
                'code'      => 400,
                'data'      => null,
                'message'   => 'Zone tidak valid. Gunakan A, B, C, D, atau E.',
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], 400);
        }

        $data = $this->model->latestPerZone($zone);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $data,
            'message'   => $zone
                ? "Kondisi lingkungan terkini Zona {$zone}."
                : 'Kondisi lingkungan terkini semua zona.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    /**
     * GET /api/environment/zones/{zone}[?from=&to=&limit=]
     * Riwayat data per zona
     */
    public function history(Request $request, Response $response, string $zone): Response
    {
        $params = $request->getQueryParams();
        $from   = $params['from']  ?? null;
        $to     = $params['to']    ?? null;
        $limit  = (int)($params['limit'] ?? 100);
        $limit  = min(max($limit, 1), 500);

        $data = $this->model->history($zone, $from, $to, $limit);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $data,
            'message'   => "Riwayat data lingkungan Zona {$zone}.",
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    /**
     * GET /health
     * Health check aktif: cek koneksi DB
     */
    public function health(Request $request, Response $response): Response
    {
        $dbOk = Database::isAlive();

        return $this->json($response, [
            'status'    => $dbOk ? 'ok' : 'degraded',
            'code'      => $dbOk ? 200 : 503,
            'data'      => [
                'service'  => 'environment-service',
                'database' => $dbOk ? 'connected' : 'disconnected',
                'port'     => 8002,
            ],
            'message'   => $dbOk ? 'Service sehat.' : 'Koneksi database gagal.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ], $dbOk ? 200 : 503);
    }

    // -------------------------------------------------------
    private function json(Response $response, array $body, int $code = 200): Response
    {
        $response->getBody()->write(
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($code);
    }
}
