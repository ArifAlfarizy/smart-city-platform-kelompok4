<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        // HARUS sama persis dengan JWT_ACCESS_SECRET di auth-service
        $this->secret = $secret ?? $_ENV['JWT_ACCESS_SECRET'] ?? getenv('JWT_ACCESS_SECRET');
    }

    public function process(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->jsonError('Access denied. No token provided.', 401);
        }

        $token = trim(substr($authHeader, 7));

        if (!$token) {
            return $this->jsonError('Access denied. Invalid token format.', 401);
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

            // simpan payload ke request attribute, bisa dipakai controller
            // $decoded->role -> 'citizen' | 'operator' | 'service'
            // $decoded->client_id -> kalau token dari client_credentials (IoT)
            $request = $request->withAttribute('auth', $decoded);
        } catch (ExpiredException $e) {
            return $this->jsonError('Token expired. Please login again.', 401);
        } catch (SignatureInvalidException $e) {
            return $this->jsonError('Invalid token.', 403);
        } catch (\Exception $e) {
            return $this->jsonError('Invalid token.', 403);
        }

        return $handler->handle($request);
    }

    private function jsonError(string $message, int $status): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error'   => $message,
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}