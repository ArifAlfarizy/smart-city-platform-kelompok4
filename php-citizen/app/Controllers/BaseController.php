<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);
    }

    protected function getJwtSecret(): string
    {
        $secret = getenv('JWT_ACCESS_SECRET');

        if ($secret === false || $secret === null || $secret === '') {
            $secret = $_ENV['JWT_ACCESS_SECRET'] ?? '';
        }

        return (string) $secret;
    }

    protected function getBearerToken(): ?string
    {
        $authHeader = $this->request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token !== '' ? $token : null;
    }

    protected function getAuthPayload(): ?array
    {
        $token = $this->getBearerToken();

        if ($token === null) {
            return null;
        }

        return $this->decodeJwtPayload($token);
    }

    protected function decodeJwtPayload(string $token): ?array
    {
        $secret = $this->getJwtSecret();
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson  = $this->base64UrlDecode($headerB64);
        $payloadJson = $this->base64UrlDecode($payloadB64);

        if ($headerJson === false || $payloadJson === false) {
            return null;
        }

        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            return null;
        }

        if (isset($payload['exp']) && is_numeric($payload['exp']) && time() >= (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }

    protected function base64UrlDecode(string $input): string|false
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'), true);
    }

    protected function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    protected function jsonSuccess(mixed $data = null, string $message = 'success', int $statusCode = 200)
    {
        $payload = [
            'status'  => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($statusCode)->setJSON($payload);
    }

    protected function jsonError(string $message, int $statusCode = 400)
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'status'  => 'error',
            'message' => $message,
        ]);
    }
}