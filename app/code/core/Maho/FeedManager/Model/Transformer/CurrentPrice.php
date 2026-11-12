<?php

/**
 * Select one price of a feed from the regular price and the current price of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_CurrentPrice extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    public const PART_PRICE = 'price';
    public const PART_SALE_PRICE = 'sale_price';
    public const PART_CURRENT = 'current';
    public const PART_ORIGINAL_PRICE = 'original_price';

    #[\Override]
    protected string $_code = 'current_price';
    #[\Override]
    protected string $_name = 'Current Price';
    #[\Override]
    protected string $_description = 'Export the price that the store charges now, with its regular price during a sale';

    #[\Override]
    protected array $_optionDefinitions = [
        'part' => [
            'label' => 'Price Field',
            'type' => 'select',
            'required' => true,
            'options' => [
                self::PART_PRICE => 'Price (regular price during a sale)',
                self::PART_SALE_PRICE => 'Sale Price (empty without a sale)',
                self::PART_CURRENT => 'Current Price',
                self::PART_ORIGINAL_PRICE => 'Original Price (empty without a sale)',
            ],
            'note' => 'Use a combined source: ["{{regular_price}}","{{final_price}}"].',
        ],
        'currency' => [
            'label' => 'Currency Code',
            'type' => 'select',
            'required' => false,
            'options' => ['code' => 'Append', 'none' => 'Omit'],
            'note' => 'Append the feed currency code, as in "25.00 USD".',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): string
    {
        $part = $options['part'] ?? '';
        if (!is_string($value) || !is_string($part)) {
            return '';
        }

        try {
            $pair = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2
            || !is_string($pair[0]) || !is_string($pair[1])) {
            return '';
        }

        $amount = $this->selectAmount($part, $pair[0], $pair[1]);
        if ($amount === null) {
            return '';
        }

        $formatted = number_format($amount, 2, '.', '');
        if (($options['currency'] ?? 'code') === 'none') {
            return $formatted;
        }

        $currency = strtoupper(trim((string) ($productData['_feed']['price_currency'] ?? '')));
        return $currency === '' ? '' : $formatted . ' ' . $currency;
    }

    private function selectAmount(string $part, string $regular, string $final): ?float
    {
        if (!is_numeric($final) || !is_finite((float) $final)) {
            return null;
        }

        $final = round((float) $final, 2);
        if ($final <= 0) {
            return null;
        }

        $regular = is_numeric($regular) && is_finite((float) $regular) ? round((float) $regular, 2) : null;
        $onSale = $regular !== null && $regular > $final;

        return match ($part) {
            self::PART_PRICE => $onSale ? $regular : $final,
            self::PART_SALE_PRICE => $onSale ? $final : null,
            self::PART_CURRENT => $final,
            self::PART_ORIGINAL_PRICE => $onSale ? $regular : null,
            default => null,
        };
    }
}
