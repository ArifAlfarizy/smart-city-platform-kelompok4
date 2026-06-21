<?php
// app/Controllers/AlertController.php
namespace App\Controllers;

use App\Models\Alert;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AlertController
{
    private Alert $model;

    public function __construct()
    {
        $this->model = new Alert();
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $zone   = isset($params['zone'])   ? strtoupper($params['zone']) : null;
        $status = $params['status'] ?? 'active';

        $data = ($status === 'all')
            ? $this->model->getAll($zone)
            : $this->model->getActive($zone);

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $data,
            'message'   => 'Daftar alert lingkungan.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    public function show(Request $request, Response $response, int $id): Response
    {
        $alert = $this->model->findById($id);

        if (empty($alert)) {
            return $this->json($response, [
                'status'    => 'error',
                'code'      => 404,
                'data'      => null,
                'message'   => "Alert #{$id} tidak ditemukan.",
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], 404);
        }

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $alert,
            'message'   => 'Detail alert.',
            'timestamp' => date('c'),
            'service'   => 'environment-service',
        ]);
    }

    public function resolve(Request $request, Response $response, int $id): Response
    {
        $resolved = $this->model->resolve($id);

        if (!$resolved) {
            return $this->json($response, [
                'status'    => 'error',
                'code'      => 404,
                'data'      => null,
                'message'   => "Alert #{$id} tidak ditemukan atau sudah di-resolve.",
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], 404);
        }

        return $this->json($response, [
            'status'    => 'success',
            'code'      => 200,
            'data'      => $this->model->findById($id),
            'message'   => "Alert #{$id} berhasil di-resolve.",
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
