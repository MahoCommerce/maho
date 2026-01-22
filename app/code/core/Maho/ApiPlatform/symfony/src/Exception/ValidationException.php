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
 * Validation Exception - 400 Bad Request
 *
 * Use when request data fails validation (missing fields, invalid format, etc.)
 */
class ValidationException extends ApiException
{
    public function __construct(
        string $message,
        ?string $field = null,
        ?string $constraint = null,
        array $additionalDetails = [],
        ?\Throwable $previous = null
    ) {
        $details = $additionalDetails;

        if ($field !== null) {
            $details['field'] = $field;
        }

        if ($constraint !== null) {
            $details['constraint'] = $constraint;
        }

        parent::__construct(
            message: $message,
            errorCode: 'validation_error',
            httpStatusCode: 400,
            details: $details,
            previous: $previous
        );
    }

    /**
     * Create exception for a required field
     */
    public static function requiredField(string $field): self
    {
        return new self(
            message: ucfirst($field) . ' is required',
            field: $field,
            constraint: 'NotBlank'
        );
    }

    /**
     * Create exception for an invalid field value
     */
    public static function invalidValue(string $field, string $reason): self
    {
        return new self(
            message: "Invalid {$field}: {$reason}",
            field: $field,
            constraint: 'Invalid'
        );
    }

    /**
     * Create exception for invalid email
     */
    public static function invalidEmail(): self
    {
        return new self(
            message: 'Invalid email address',
            field: 'email',
            constraint: 'Email'
        );
    }

    /**
     * Create exception for password too short
     */
    public static function passwordTooShort(int $minLength): self
    {
        return new self(
            message: "Password must be at least {$minLength} characters",
            field: 'password',
            constraint: 'MinLength',
            additionalDetails: ['minLength' => $minLength]
        );
    }
}
