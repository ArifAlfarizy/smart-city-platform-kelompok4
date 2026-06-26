<?php
// app/Controllers/EnvironmentController.php
namespace App\Controllers;

use App\Models\EnvironmentReading;
use App\Models\Database;
use App\Services\RabbitMQPublisher;
use App\Validators\SensorValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EnvironmentController
{
    private EnvironmentReading $model;
    private RabbitMQPublisher  $publisher;
    private SensorValidator    $validator;

    public function __construct()
    {
        $this->model     = new EnvironmentReading();
        $this->publisher = new RabbitMQPublisher();
        $this->validator = new SensorValidator();
    }

    public function sensor(Request $request, Response $response): Response
    {
        $raw = (array)($request->getParsedBody() ?? []);

        try {
            $data = $this->validator->validate($raw);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, [
                'status'  => 'error',
                'code'    => 422,
                'data'    => null,
                'message' => $e->getMessage(),
            ], 422);
        }

        $data['flood_status'] = $this->computeFloodStatus($data['water_level']);

        $record = $this->model->create($data);

        $alerts = $this->generateAlerts($record);

        $this->publisher->publish('environment.sensor.received', [
            'sensor_id'    => $record['sensor_id'],
            'rainfall'     => (float)$record['rainfall'],
            'water_level'  => (float)$record['water_level'],
            'flood_status' => $record['flood_status'],
            'recorded_at'  => $record['recorded_at'],
        ]);

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 201,
            'data'    => array_merge($record, [
                'alerts_created' => count($alerts),
            ]),
            'message' => 'Data sensor berhasil disimpan.',
        ], 201);
    }

    public function current(Request $request, Response $response): Response
    {
        $data = $this->model->latest();

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $data,
            'message' => 'Data environment terkini.',
        ]);
    }

    public function history(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $from   = $params['from']  ?? null;
        $to     = $params['to']    ?? null;
        $limit  = (int)($params['limit'] ?? 50);
        $limit  = min(max($limit, 1), 500);

        $data = $this->model->history($from, $to, $limit);

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $data,
            'message' => 'Riwayat data environment berhasil diambil.',
        ]);
    }

    public function floodStatus(Request $request, Response $response): Response
    {
        $data   = $this->model->latest();
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

    public function alerts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? 'active';

        $data = $this->model->getAlerts($status);

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $data,
            'message' => 'Daftar alert berhasil diambil.',
        ]);
    }

    public function alertDetail(Request $request, Response $response, array $args): Response
    {
        $id   = (int)($args['id'] ?? 0);
        $data = $this->model->getAlertById($id);

        if (!$data) {
            return $this->json($response, [
                'status'  => 'error',
                'code'    => 404,
                'data'    => null,
                'message' => "Alert ID {$id} tidak ditemukan.",
            ], 404);
        }

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => $data,
            'message' => 'Detail alert.',
        ]);
    }

    public function resolveAlert(Request $request, Response $response, array $args): Response
    {
        $id      = (int)($args['id'] ?? 0);
        $success = $this->model->resolveAlert($id);

        if (!$success) {
            return $this->json($response, [
                'status'  => 'error',
                'code'    => 404,
                'data'    => null,
                'message' => "Alert ID {$id} tidak ditemukan atau sudah resolved.",
            ], 404);
        }

        return $this->json($response, [
            'status'  => 'success',
            'code'    => 200,
            'data'    => ['id' => $id, 'status' => 'resolved'],
            'message' => "Alert ID {$id} berhasil di-resolve.",
        ]);
    }

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

    private function generateAlerts(array $record): array
    {
        $alerts = [];

        if ((float)$record['water_level'] >= 50) {
            $severity = (float)$record['water_level'] >= 100 ? 'CRITICAL' : 'WARNING';
            $msg      = "Ketinggian air {$record['water_level']} cm. "
                      . "Status: {$record['flood_status']}. Waspadai banjir.";

            $alert = $this->model->createAlert([
                'alert_type' => 'FLOOD_HIGH',
                'value'      => (string)$record['water_level'],
                'threshold'  => '50',
                'message'    => $msg,
                'severity'   => $severity,
            ]);

            if ($alert) {
                $alerts[] = $alert;
                $this->publisher->publish('environment.alert.created', $alert);
            }
        }

        if ((float)$record['rainfall'] > 30) {
            $alert = $this->model->createAlert([
                'alert_type' => 'RAIN_HEAVY',
                'value'      => (string)$record['rainfall'],
                'threshold'  => '30',
                'message'    => "Intensitas hujan {$record['rainfall']} mm/h. Waspadai genangan.",
                'severity'   => 'WARNING',
            ]);

            if ($alert) {
                $alerts[] = $alert;
                $this->publisher->publish('environment.alert.created', $alert);
            }
        }

        return array_filter($alerts);
    }

    private function json(Response $response, array $body, int $code = 200): Response
    {
        $response->getBody()->write(json_encode(
            array_merge($body, [
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ]),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($code);
    }
}