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
 * Authentication Exception - 401 Unauthorized
 *
 * Use when user is not authenticated or credentials are invalid.
 */
class AuthenticationException extends ApiException
{
    public function __construct(
        string $message = 'Authentication required',
        string $errorCode = 'authentication_required',
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            httpStatusCode: 401,
            details: $details,
            previous: $previous
        );
    }

    /**
     * Create exception for missing authentication
     */
    public static function required(): self
    {
        return new self('Authentication required');
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(): self
    {
        return new self(
            message: 'Invalid email or password',
            errorCode: 'invalid_credentials'
        );
    }

    /**
     * Create exception for invalid token
     */
    public static function invalidToken(): self
    {
        return new self(
            message: 'Invalid or expired token',
            errorCode: 'invalid_token'
        );
    }

    /**
     * Create exception for expired token
     */
    public static function expiredToken(): self
    {
        return new self(
            message: 'Token has expired',
            errorCode: 'expired_token'
        );
    }

    /**
     * Create exception for invalid form key
     */
    public static function invalidFormKey(): self
    {
        return new self(
            message: 'Invalid form key',
            errorCode: 'invalid_form_key'
        );
    }
}
