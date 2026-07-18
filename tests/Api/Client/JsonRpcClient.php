<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

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
        // login is a top-level JSON-RPC method, not a resource call, so it is
        // invoked directly rather than wrapped in the `call` dispatcher.
        $response = $this->invoke('login', [$username, $password]);

        if (!$response->isSuccess()) {
            throw new \Exception('Login failed: ' . $response->getError()['message'] ?? 'Unknown error');
        }

        return $response->getResult();
    }

    /**
     * Invoke an API resource method through the `call` dispatcher:
     * call($sessionId, $apiPath, $args). Without a session id the request is
     * still well-formed JSON-RPC (used for reachability probes).
     */
    public function call(string $method, array $params = [], ?string $sessionId = null): JsonRpcResponse
    {
        return $this->invoke('call', $sessionId ? [$sessionId, $method, $params] : [$method, $params]);
    }

    public function multiCall(array $calls, ?string $sessionId = null): array
    {
        $multiCallParams = [];
        foreach ($calls as $call) {
            $multiCallParams[] = [$call[0], $call[1] ?? []];
        }

        // multiCall is a top-level method taking ($sessionId, $calls).
        $response = $this->invoke('multiCall', $sessionId ? [$sessionId, $multiCallParams] : [$multiCallParams]);

        if (!$response->isSuccess()) {
            throw new \Exception('MultiCall failed: ' . $response->getError()['message'] ?? 'Unknown error');
        }

        return $response->getResult();
    }

    /**
     * Send a raw JSON-RPC 2.0 request for the named server method.
     */
    private function invoke(string $rpcMethod, array $params): JsonRpcResponse
    {
        return $this->makeRequest([
            'jsonrpc' => '2.0',
            'method' => $rpcMethod,
            'params' => $params,
            'id' => $this->requestId++,
        ]);
    }

    private function makeRequest(array $payload): JsonRpcResponse
    {
        // The base URL is the api.php entry point; the legacy JSON-RPC adapter
        // is selected via the ?type=jsonrpc query parameter (mirrors the
        // /api/jsonrpc .htaccess rewrite that maps to api.php?type=jsonrpc).
        $url = $this->baseUrl . '?type=jsonrpc';

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
