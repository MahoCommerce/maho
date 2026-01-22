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
 * Authorization Exception - 403 Forbidden
 *
 * Use when user is authenticated but lacks permission for the requested action.
 */
class AuthorizationException extends ApiException
{
    public function __construct(
        string $message = 'Access denied',
        string $errorCode = 'access_denied',
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            httpStatusCode: 403,
            details: $details,
            previous: $previous
        );
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(?string $resource = null): self
    {
        $message = $resource
            ? "You do not have permission to access {$resource}"
            : 'You do not have permission to perform this action';

        return new self(
            message: $message,
            errorCode: 'insufficient_permissions',
            details: $resource ? ['resource' => $resource] : []
        );
    }

    /**
     * Create exception for admin-only access
     */
    public static function adminRequired(): self
    {
        return new self(
            message: 'Admin access required',
            errorCode: 'admin_required'
        );
    }

    /**
     * Create exception for accessing another user's resource
     */
    public static function notOwner(string $resource): self
    {
        return new self(
            message: "You can only access your own {$resource}",
            errorCode: 'not_owner',
            details: ['resource' => $resource]
        );
    }
}
