<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_GooglePrice extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    #[\Override]
    protected string $_code = 'google_price';
    #[\Override]
    protected string $_name = 'Google Price';
    #[\Override]
    protected string $_description = 'Export the current native price and its regular price for Google';

    #[\Override]
    protected array $_optionDefinitions = [
        'part' => [
            'label' => 'Price Field',
            'type' => 'select',
            'required' => true,
            'options' => ['price' => 'Price', 'sale_price' => 'Sale Price'],
            'note' => 'Use a combined source: ["{{regular_price}}","{{final_price}}"].',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): string
    {
        $part = $options['part'] ?? '';
        if (!is_string($value) || !in_array($part, ['price', 'sale_price'], true)) {
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

        $prices = $this->currentPrices($pair[0], $pair[1]);
        $amount = $prices[$part];
        $currency = strtoupper(trim((string) ($productData['_feed']['price_currency'] ?? '')));
        if ($amount === null || $currency === '') {
            return '';
        }

        return number_format($amount, 2, '.', '') . ' ' . $currency;
    }

    /**
     * @return array{price: float|null, sale_price: float|null}
     */
    private function currentPrices(string $regular, string $final): array
    {
        if (!is_numeric($final) || !is_finite((float) $final) || (float) $final <= 0) {
            return ['price' => null, 'sale_price' => null];
        }

        $final = round((float) $final, 2);
        if ($final <= 0) {
            return ['price' => null, 'sale_price' => null];
        }

        $regular = is_numeric($regular) && is_finite((float) $regular) ? round((float) $regular, 2) : null;
        if ($regular !== null && $regular > $final) {
            return ['price' => $regular, 'sale_price' => $final];
        }

        return ['price' => $final, 'sale_price' => null];
    }
}
