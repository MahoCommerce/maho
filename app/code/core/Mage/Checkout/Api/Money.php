<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

/**
 * Money value object for price representation in GraphQL
 *
 * Provides a consistent structure for monetary values with:
 * - value: the numeric amount
 * - formatted: human-readable string (e.g., "$29.99")
 * - currency: the currency code (e.g., "AUD")
 */
class Money extends \Maho\ApiPlatform\Resource
{
    public ?float $value = null;
    public ?string $formatted = null;
    public ?string $currency = null;

    public function __construct(?float $value = null, ?string $currency = null)
    {
        $this->value = $value;
        $this->currency = $currency ?? 'AUD';

        if ($value !== null) {
            $this->formatted = $this->format($value, $this->currency);
        }
    }

    /**
     * Create Money instance from a float value
     */
    public static function fromFloat(?float $value, ?string $currency = null): self
    {
        return new self($value, $currency);
    }

    /**
     * Format the money value as a human-readable string
     */
    private function format(float $value, string $currency): string
    {
        $symbols = [
            'AUD' => '$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'NZD' => '$',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . number_format($value, 2);
    }
}
