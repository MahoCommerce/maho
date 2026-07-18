<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_CurrencyConvert extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'currency_convert';
    protected string $_name = 'Convert Currency';
    protected string $_description = 'Convert a numeric price from one currency to another using directory rates';

    protected array $_optionDefinitions = [
        'to' => [
            'label' => 'Target Currency',
            'type' => 'text',
            'required' => true,
            'note' => 'ISO currency code to convert to (e.g. NZD)',
        ],
        'from' => [
            'label' => 'Source Currency',
            'type' => 'text',
            'required' => false,
            'note' => 'ISO currency code to convert from (default: store base currency)',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $to = strtoupper(trim((string) ($options['to'] ?? '')));
        if ($to === '') {
            return $value;
        }
        $from = strtoupper(trim((string) ($options['from'] ?? '')));
        if ($from === '') {
            $from = (string) Mage::app()->getStore()->getBaseCurrencyCode();
        }
        if ($from === $to) {
            return $value;
        }

        $rate = Mage::getModel('directory/currency')->load($from)->getRate($to);
        if (!$rate) {
            return $value;
        }

        return round((float) $value * (float) $rate, 4);
    }
}
