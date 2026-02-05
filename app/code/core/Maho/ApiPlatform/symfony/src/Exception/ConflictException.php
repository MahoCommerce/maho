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
 * Conflict Exception - 409 Conflict
 *
 * Use when the request conflicts with current state (e.g., duplicate email, already exists).
 */
class ConflictException extends ApiException
{
    public function __construct(
        string $message = 'Conflict with current state',
        string $errorCode = 'conflict',
        array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            httpStatusCode: 409,
            details: $details,
            previous: $previous,
        );
    }

    /**
     * Create exception for duplicate email
     */
    public static function duplicateEmail(string $email): self
    {
        return new self(
            message: 'An account with this email already exists',
            errorCode: 'duplicate_email',
            details: ['email' => $email],
        );
    }

    /**
     * Create exception for duplicate resource
     */
    public static function duplicate(string $type, string $field, string $value): self
    {
        return new self(
            message: ucfirst($type) . " with this {$field} already exists",
            errorCode: "duplicate_{$type}",
            details: [$field => $value],
        );
    }

    /**
     * Create exception for invalid state transition
     */
    public static function invalidStateTransition(string $from, string $to): self
    {
        return new self(
            message: "Cannot transition from '{$from}' to '{$to}'",
            errorCode: 'invalid_state_transition',
            details: ['from' => $from, 'to' => $to],
        );
    }

    /**
     * Create exception for coupon already applied
     */
    public static function couponAlreadyApplied(): self
    {
        return new self(
            message: 'A coupon is already applied to this cart',
            errorCode: 'coupon_already_applied',
        );
    }

    /**
     * Create exception for gift card already applied
     */
    public static function giftCardAlreadyApplied(string $code): self
    {
        return new self(
            message: 'This gift card is already applied to the cart',
            errorCode: 'giftcard_already_applied',
            details: ['code' => $code],
        );
    }
}
