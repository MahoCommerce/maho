<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Exception;

/**
 * Base API Exception.
 *
 * All API-specific exceptions should extend this class.
 * Provides structured error response data for consistent API error handling.
 *
 * @phpstan-type ErrorDetails array<string, int|string|list<array{field: string, message: string}>>
 */
class ApiException extends \RuntimeException
{
    /**
     * @param ErrorDetails $details
     */
    public function __construct(
        string $message,
        protected string $errorCode = 'api_error',
        int $httpStatusCode = 500,
        protected array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->code;
    }

    /**
     * @return ErrorDetails
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Convert exception to array for JSON response
     *
     * @return array{error: string, message: string, code: int, details?: ErrorDetails}
     */
    public function toArray(): array
    {
        $result = [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'code' => $this->code,
        ];

        if (!empty($this->details)) {
            $result['details'] = $this->details;
        }

        return $result;
    }
}
