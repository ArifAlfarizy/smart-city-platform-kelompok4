<?php
// app/Controllers/ZoneController.php
namespace App\Controllers;

use App\Models\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ZoneController
{
    /**
     * GET /api/environment/zones
     */
    public function index(Request $request, Response $response): Response
    {
        $db   = Database::getConnection();
        $stmt = $db->query('SELECT * FROM zones ORDER BY name');
        $data = $stmt->fetchAll();

        $response->getBody()->write(
            json_encode([
                'status'    => 'success',
                'code'      => 200,
                'data'      => $data,
                'message'   => 'Daftar zona kota.',
                'timestamp' => date('c'),
                'service'   => 'environment-service',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(200);
    }
}
