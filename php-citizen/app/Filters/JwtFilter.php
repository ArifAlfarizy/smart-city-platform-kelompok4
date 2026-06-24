<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->jsonError('Access denied. No token provided.', 401);
        }

        $token = trim($matches[1]);

        if ($token === '' || !$this->isValidJwt($token)) {
            return $this->jsonError('Invalid token.', 403);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function isValidJwt(string $token): bool
    {
        $secret = getenv('JWT_ACCESS_SECRET');

        if ($secret === false || $secret === null || $secret === '') {
            $secret = $_ENV['JWT_ACCESS_SECRET'] ?? '';
        }

        if ($secret === '') {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson  = $this->base64UrlDecode($headerB64);
        $payloadJson = $this->base64UrlDecode($payloadB64);

        if ($headerJson === false || $payloadJson === false) {
            return false;
        }

        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            return false;
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return false;
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            return false;
        }

        if (isset($payload['exp']) && is_numeric($payload['exp']) && time() >= (int) $payload['exp']) {
            return false;
        }

        return true;
    }

    private function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'), true);
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function jsonError(string $message, int $statusCode)
    {
        $response = service('response');
        return $response->setStatusCode($statusCode)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}