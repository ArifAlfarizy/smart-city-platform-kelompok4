<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthMiddleware;
use App\Middleware\RequireRoleMiddleware;

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

$app->add(function (Request $request, $handler): Response {
    if ($request->getMethod() === 'OPTIONS') {
        return (new \Slim\Psr7\Response())
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(204);
    }
    return $handler->handle($request)
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

$app->get('/health', function (Request $req, Response $res): Response {
    return (new App\Controllers\EnvironmentController())->health($req, $res);
});

$app->group('/api/environment', function (RouteCollectorProxy $group): void {

    $group->post('/sensor', function (Request $req, Response $res): Response {
        return (new App\Controllers\EnvironmentController())->sensor($req, $res);
    });

    $group->get('/current', function (Request $req, Response $res): Response {
        return (new App\Controllers\EnvironmentController())->current($req, $res);
    });

    $group->get('/history', function (Request $req, Response $res): Response {
        return (new App\Controllers\EnvironmentController())->history($req, $res);
    });

    $group->get('/flood-status', function (Request $req, Response $res): Response {
        return (new App\Controllers\EnvironmentController())->floodStatus($req, $res);
    });

    $group->get('/alerts', function (Request $req, Response $res): Response {
        return (new App\Controllers\EnvironmentController())->alerts($req, $res);
    });

    $group->get('/alerts/{id}', function (Request $req, Response $res, array $args): Response {
        return (new App\Controllers\EnvironmentController())->alertDetail($req, $res, $args);
    });

    $group->put('/alerts/{id}/resolve', function (Request $req, Response $res, array $args): Response {
        return (new App\Controllers\EnvironmentController())->resolveAlert($req, $res, $args);
    })->add(new RequireRoleMiddleware(['operator']));

})->add(new AuthMiddleware());

$app->run();