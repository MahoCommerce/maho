<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tests\Api\Client;

use Tests\Api\Client\Response\JsonRpcResponse;

class JsonRpcClient
{
    private string $baseUrl;
    private array $defaultHeaders;
    private ?string $username = null;
    private ?string $password = null;
    private int $requestId = 1;
    private int $timeout = 30;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function login(string $username, string $password): string
    {
        $response = $this->call('login', [$username, $password]);

        if (!$response->isSuccess()) {
            throw new \Exception('Login failed: ' . $response->getError()['message'] ?? 'Unknown error');
        }

        return $response->getResult();
    }

    public function call(string $method, array $params = [], ?string $sessionId = null): JsonRpcResponse
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => $sessionId ? [$sessionId, $method, $params] : [$method, $params],
            'id' => $this->requestId++,
        ];

        return $this->makeRequest($payload);
    }

    public function multiCall(array $calls, ?string $sessionId = null): array
    {
        $multiCallParams = [];
        foreach ($calls as $call) {
            $multiCallParams[] = [$call[0], $call[1] ?? []];
        }

        $response = $this->call('multiCall', [$multiCallParams], $sessionId);

        if (!$response->isSuccess()) {
            throw new \Exception('MultiCall failed: ' . $response->getError()['message'] ?? 'Unknown error');
        }

        return $response->getResult();
    }

    private function makeRequest(array $payload): JsonRpcResponse
    {
        $url = $this->baseUrl . '/jsonrpc';

        $context = [
            'http' => [
                'method' => 'POST',
                'header' => $this->buildHeaders(),
                'content' => json_encode($payload),
                'timeout' => $this->timeout,
                'ignore_errors' => true, // Don't throw on HTTP errors
            ],
        ];

        $context = stream_context_create($context);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \Exception('Failed to make HTTP request to ' . $url);
        }

        // Get HTTP response code from headers
        $httpCode = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }

        return new JsonRpcResponse($response, $httpCode, $payload['id']);
    }

    private function buildHeaders(): string
    {
        $headers = [];

        foreach ($this->defaultHeaders as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }

        if ($this->username && $this->password) {
            $auth = base64_encode($this->username . ':' . $this->password);
            $headers[] = "Authorization: Basic {$auth}";
        }

        return implode("\r\n", $headers) . "\r\n";
    }
}
