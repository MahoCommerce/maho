<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tests\Api\Client\Response;

class JsonRpcResponse
{
    private array $data;
    private int $httpCode;
    private string|int $expectedId;
    private string $rawResponse;

    public function __construct(string $response, int $httpCode, string|int $expectedId)
    {
        $this->rawResponse = $response;
        $this->httpCode = $httpCode;
        $this->expectedId = $expectedId;

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON response: ' . json_last_error_msg());
        }

        $this->data = $decoded;
        $this->validateResponse();
    }

    public function isSuccess(): bool
    {
        return $this->httpCode >= 200 && $this->httpCode < 300 && !$this->hasError();
    }

    public function hasError(): bool
    {
        return isset($this->data['error']);
    }

    public function getResult(): mixed
    {
        if ($this->hasError()) {
            throw new \RuntimeException('Cannot get result from error response');
        }

        return $this->data['result'] ?? null;
    }

    public function getError(): ?array
    {
        return $this->data['error'] ?? null;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getId(): string|int
    {
        return $this->data['id'] ?? null;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getJsonRpcVersion(): string
    {
        return $this->data['jsonrpc'] ?? '';
    }

    private function validateResponse(): void
    {
        // Validate JSON-RPC 2.0 structure
        if (!isset($this->data['jsonrpc']) || $this->data['jsonrpc'] !== '2.0') {
            throw new \InvalidArgumentException('Invalid JSON-RPC version');
        }

        // Validate ID matches
        if (isset($this->data['id']) && $this->data['id'] !== $this->expectedId) {
            throw new \InvalidArgumentException(
                sprintf('Response ID mismatch: expected %s, got %s', $this->expectedId, $this->data['id']),
            );
        }

        // Must have either result or error
        if (!isset($this->data['result']) && !isset($this->data['error'])) {
            throw new \InvalidArgumentException('Response must contain either result or error');
        }

        // Cannot have both result and error
        if (isset($this->data['result']) && isset($this->data['error'])) {
            throw new \InvalidArgumentException('Response cannot contain both result and error');
        }
    }
}
