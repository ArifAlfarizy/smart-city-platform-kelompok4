<?php
// app/Controllers/VehicleController.php
namespace App\Controllers;

use App\Models\VehicleCount;
use App\Services\RabbitMQPublisher;
use App\Validators\VehicleValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VehicleController
{
    private VehicleCount      $model;
    private RabbitMQPublisher $publisher;
    private VehicleValidator  $validator;

    public function __construct()
    {
        $this->model     = new VehicleCount();
        $this->publisher = new RabbitMQPublisher();
        $this->validator = new VehicleValidator();
    }

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

        $this->publisher->publish('vehicle.count.received', [
            'id'             => $record['id'],
            'sensor_id'      => $record['sensor_id'],
            'zone'           => $record['zone'],
            'vehicle_count'  => (int)$record['vehicle_count'],
            'interval_sec'   => (int)$record['interval_sec'],
            'traffic_status' => $record['traffic_status'],
            'rain_intensity' => (float)$record['rain_intensity'],
            'rain_status'    => $record['rain_status'],
            'flood_level'    => (float)$record['flood_level'],
            'timestamp'      => $record['recorded_at'],
        ]);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 201,
            'data'      => $record,
            'message'   => 'Data kendaraan berhasil disimpan.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ], 201);
    }

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
                ? "Status lalu lintas terkini Zona {$zone}."
                : 'Status lalu lintas terkini semua zona.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    public function history(Request $request, Response $response, string $zone): Response
    {
        $params = $request->getQueryParams();
        $from   = $params['from']  ?? null;
        $to     = $params['to']    ?? null;
        $limit  = (int)($params['limit'] ?? 200);
        $limit  = min(max($limit, 1), 1000); // ML butuh data banyak, max 1000

        $data = $this->model->history($zone, $from, $to, $limit);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $data,
            'message'   => "Riwayat data kendaraan Zona {$zone}.",
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    public function aggregate(Request $request, Response $response, string $zone): Response
    {
        $params = $request->getQueryParams();
        $period = $params['period'] ?? '1h';

        $validPeriods = ['1h', '6h', '24h', '7d'];
        if (!in_array($period, $validPeriods, true)) {
            return $this->json($response, [
                'status'    => 'error',
                'code'      => 400,
                'data'      => null,
                'message'   => 'Period tidak valid. Gunakan: 1h, 6h, 24h, 7d.',
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], 400);
        }

        $data = $this->model->aggregate($zone, $period);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $data,
            'message'   => "Agregat lalu lintas Zona {$zone} periode {$period}.",
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

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
