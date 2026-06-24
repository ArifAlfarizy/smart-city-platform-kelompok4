<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    logErrors: true,
    logErrorDetails: true
);
$errorMiddleware->getDefaultErrorHandler()->forceContentType('application/json');

// CORS
$app->add(function (Request $request, $handler): Response {
    if ($request->getMethod() === 'OPTIONS') {
        return (new \Slim\Psr7\Response())
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(204);
    }
    return $handler->handle($request)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Health check — tanpa auth (buat Docker healthcheck)
$app->get('/health', function (Request $request, Response $response): Response {
    $controller = new App\Controllers\EnvironmentController();
    return $controller->health($request, $response);
});

// Semua endpoint environment wajib token
$app->group('', function (RouteCollectorProxy $group): void {
    // POST /environment-data — dari IoT (Rain Sensor + Ultrasonic)
    $group->post('/environment-data', function (Request $request, Response $response): Response {
        return (new App\Controllers\EnvironmentController())->store($request, $response);
    });

    // GET /environment-status — data terkini (rainfall + water_level)
    $group->get('/environment-status', function (Request $request, Response $response): Response {
        return (new App\Controllers\EnvironmentController())->status($request, $response);
    });

    // GET /flood-status — status banjir berdasarkan water_level
    $group->get('/flood-status', function (Request $request, Response $response): Response {
        return (new App\Controllers\EnvironmentController())->floodStatus($request, $response);
    });

})->add(new AuthMiddleware());

$app->run();