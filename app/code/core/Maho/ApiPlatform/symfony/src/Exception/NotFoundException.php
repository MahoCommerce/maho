<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Exception;

/**
 * Not Found Exception - 404 Not Found
 *
 * Use when a requested resource does not exist.
 */
class NotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Resource not found',
        string $errorCode = 'not_found',
        array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            httpStatusCode: 404,
            details: $details,
            previous: $previous,
        );
    }

    /**
     * Create exception for a specific resource type
     */
    public static function resource(string $type, int|string|null $id = null): self
    {
        $message = $id !== null
            ? ucfirst($type) . " with ID {$id} not found"
            : ucfirst($type) . ' not found';

        return new self(
            message: $message,
            errorCode: "{$type}_not_found",
            details: $id !== null ? ['id' => $id] : [],
        );
    }

    /**
     * Create exception for cart not found
     */
    public static function cart(int|string|null $id = null): self
    {
        return self::resource('cart', $id);
    }

    /**
     * Create exception for product not found
     */
    public static function product(int|string|null $id = null): self
    {
        return self::resource('product', $id);
    }

    /**
     * Create exception for customer not found
     */
    public static function customer(int|string|null $id = null): self
    {
        return self::resource('customer', $id);
    }

    /**
     * Create exception for order not found
     */
    public static function order(int|string|null $id = null): self
    {
        return self::resource('order', $id);
    }

    /**
     * Create exception for gift card not found
     */
    public static function giftCard(string $code): self
    {
        return new self(
            message: 'Gift card not found',
            errorCode: 'giftcard_not_found',
            details: ['code' => $code],
        );
    }

    /**
     * Create exception for cart item not found
     */
    public static function cartItem(int $itemId): self
    {
        return new self(
            message: 'Cart item not found',
            errorCode: 'cart_item_not_found',
            details: ['itemId' => $itemId],
        );
    }
}
