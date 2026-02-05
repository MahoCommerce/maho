<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Exception;

/**
 * Base API Exception
 *
 * All API-specific exceptions should extend this class.
 * Provides structured error response data for consistent API error handling.
 */
class ApiException extends \RuntimeException
{
    protected string $errorCode;
    protected array $details = [];

    public function __construct(
        string $message,
        string $errorCode = 'api_error',
        int $httpStatusCode = 500,
        array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->code;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Convert exception to array for JSON response
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
