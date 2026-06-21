<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthMiddleware;
use App\Middleware\RequireRoleMiddleware;

require __DIR__ . '/../vendor/autoload.php';

// ---------- Load .env ----------
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// ---------- Create Slim App ----------
$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    logErrors: true,
    logErrorDetails: true
);

$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

// CORS Middleware
$app->add(function (Request $request, $handler): Response {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(204);
    }

    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Health check — TIDAK perlu auth, dipakai Docker healthcheck
$app->get('/health', function (Request $request, Response $response): Response {
    $controller = new App\Controllers\SensorController();
    return $controller->health($request, $response);
});

// (baik user login biasa, maupun IoT device via client_credentials grant)
$app->group('/api/environment', function (RouteCollectorProxy $group): void {

    // POST /api/environment/sensor — diisi oleh IoT/Node-RED (token client_credentials)
    $group->post('/sensor', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\SensorController();
        return $controller->store($request, $response);
    });

    // GET /api/environment/current[?zone=A]
    $group->get('/current', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\SensorController();
        return $controller->current($request, $response);
    });

    // GET /api/environment/zones
    $group->get('/zones', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\ZoneController();
        return $controller->index($request, $response);
    });

    // GET /api/environment/zones/{zone}[?from=&to=&limit=]
    $group->get('/zones/{zone:[A-Ea-e]}', function (Request $request, Response $response, array $args): Response {
        $controller = new App\Controllers\SensorController();
        return $controller->history($request, $response, strtoupper($args['zone']));
    });

    // GET /api/environment/alerts[?zone=A&status=active]
    $group->get('/alerts', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\AlertController();
        return $controller->index($request, $response);
    });

    // GET /api/environment/alerts/{id}
    $group->get('/alerts/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $controller = new App\Controllers\AlertController();
        return $controller->show($request, $response, (int)$args['id']);
    });

    // PUT /api/environment/alerts/{id}/resolve — KHUSUS role operator
    $group->put('/alerts/{id:[0-9]+}/resolve', function (Request $request, Response $response, array $args): Response {
        $controller = new App\Controllers\AlertController();
        return $controller->resolve($request, $response, (int)$args['id']);
    })->add(new RequireRoleMiddleware(['operator']));

    // POST /api/environment/vehicle — ingest dari Node-RED (IR counter), token client_credentials
    $group->post('/vehicle', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\VehicleController();
        return $controller->store($request, $response);
    });

    // GET /api/environment/vehicle/current[?zone=A]
    $group->get('/vehicle/current', function (Request $request, Response $response): Response {
        $controller = new App\Controllers\VehicleController();
        return $controller->current($request, $response);
    });

    // GET /api/environment/vehicle/zones/{zone}[?from=&to=&limit=]
    $group->get('/vehicle/zones/{zone:[A-Ea-e]}', function (Request $request, Response $response, array $args): Response {
        $controller = new App\Controllers\VehicleController();
        return $controller->history($request, $response, strtoupper($args['zone']));
    });

    // GET /api/environment/vehicle/zones/{zone}/aggregate[?period=1h|6h|24h|7d]
    $group->get('/vehicle/zones/{zone:[A-Ea-e]}/aggregate', function (Request $request, Response $response, array $args): Response {
        $controller = new App\Controllers\VehicleController();
        return $controller->aggregate($request, $response, strtoupper($args['zone']));
    });

})->add(new AuthMiddleware()); 

$app->run();