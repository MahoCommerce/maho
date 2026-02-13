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

namespace Maho\ApiPlatform\ApiResource;

/**
 * Money value object for price representation in GraphQL
 *
 * Provides a consistent structure for monetary values with:
 * - value: the numeric amount
 * - formatted: human-readable string (e.g., "$29.99")
 * - currency: the currency code (e.g., "AUD")
 */
class Money
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
