<?php
namespace App\Controllers;

use App\Models\EnvironmentReading;
use App\Models\Database;
use App\Services\RabbitMQPublisher;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EnvironmentController
{
    private EnvironmentReading $model;
    private RabbitMQPublisher  $publisher;

    public function __construct()
    {
        $this->model     = new EnvironmentReading();
        $this->publisher = new RabbitMQPublisher();
    }

    // POST /environment-data
    public function store(Request $request, Response $response): Response
    {
        $raw = (array)($request->getParsedBody() ?? []);

        // Validasi sederhana
        $errors = [];
        if (!isset($raw['rainfall']) || !is_numeric($raw['rainfall']) || (float)$raw['rainfall'] < 0) {
            $errors[] = 'Field rainfall wajib diisi dan harus >= 0 (mm/h).';
        }
        if (!isset($raw['water_level']) || !is_numeric($raw['water_level']) || (float)$raw['water_level'] < 0) {
            $errors[] = 'Field water_level wajib diisi dan harus >= 0 (cm).';
        }

        if (!empty($errors)) {
            return $this->json($response, [
                'status'  => 'error',
                'code'    => 422,
                'data'    => null,
                'message' => implode(' ', $errors),
            ], 422);
        }

        $rainfall    = (float)$raw['rainfall'];
        $water_level = (float)$raw['water_level'];
        $sensor_id   = $raw['sensor_id'] ?? 'UNKNOWN';

        // Tentukan flood_status
        $flood_status = $this->computeFloodStatus($water_level);

        $record = $this->model->create([
            'sensor_id'    => $sensor_id,
            'rainfall'     => $rainfall,
            'water_level'  => $water_level,
            'flood_status' => $flood_status,
        ]);

        // Publish ke RabbitMQ → water.updated
        $this->publisher->publish('water.updated', [
            'sensor_id'    => $record['sensor_id'],
            'rainfall'     => (float)$record['rainfall'],
            'water_level'  => (float)$record['water_level'],
            'flood_status' => $record['flood_status'],
            'timestamp'    => $record['recorded_at'],
        ]);

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 201,
            'data'    => $record,
            'message' => 'Data environment berhasil disimpan.',
        ], 201);
    }

    // GET /environment-status
    public function status(Request $request, Response $response): Response
    {
        $data = $this->model->latest();

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $data,
            'message' => 'Status environment terkini.',
        ]);
    }

    // GET /flood-status
    public function floodStatus(Request $request, Response $response): Response
    {
        $data = $this->model->latest();

        $result = null;
        if ($data) {
            $result = [
                'water_level'  => (float)$data['water_level'],
                'flood_status' => $data['flood_status'],
                'rainfall'     => (float)$data['rainfall'],
                'recorded_at'  => $data['recorded_at'],
            ];
        }

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $result,
            'message' => 'Status banjir terkini.',
        ]);
    }

    // GET /health
    public function health(Request $request, Response $response): Response
    {
        $dbOk = Database::isAlive();

        return $this->json($response, [
            'status' => $dbOk ? 'ok' : 'degraded',
            'code'   => $dbOk ? 200 : 503,
            'data'   => [
                'service'  => 'environment-service',
                'database' => $dbOk ? 'connected' : 'disconnected',
            ],
            'message' => $dbOk ? 'Service sehat.' : 'DB tidak terhubung.',
        ], $dbOk ? 200 : 503);
    }

    private function computeFloodStatus(float $water_level): string
    {
        if ($water_level >= 150) return 'Bahaya';
        if ($water_level >= 100) return 'Siaga';
        if ($water_level >= 50)  return 'Waspada';
        return 'Aman';
    }

    private function json(Response $response, array $body, int $code = 200): Response
    {
        $response->getBody()->write(json_encode(
            array_merge($body, ['timestamp' => date('c'), 'service' => 'environment-service']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($code);
    }
}