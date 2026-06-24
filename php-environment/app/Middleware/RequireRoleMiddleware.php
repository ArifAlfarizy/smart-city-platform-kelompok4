<?php
// php-environment/app/Middleware/RequireRoleMiddleware.php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

class RequireRoleMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, Handler $handler): Response
    {
        $auth = $request->getAttribute('auth');
        $role = $auth->role ?? null;

        if (!$role || !in_array($role, $this->allowedRoles, true)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => 'Forbidden. Insufficient role.',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        return $handler->handle($request);
    }
}