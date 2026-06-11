<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_Round extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'round';
    protected string $_name = 'Round Number';
    protected string $_description = 'Round numeric values';

    protected array $_optionDefinitions = [
        'precision' => [
            'label' => 'Decimal Places',
            'type' => 'text',
            'required' => false,
            'note' => 'Number of decimal places (default: 0)',
        ],
        'mode' => [
            'label' => 'Rounding Mode',
            'type' => 'select',
            'required' => false,
            'options' => [
                'round' => 'Round (standard)',
                'ceil' => 'Ceiling (round up)',
                'floor' => 'Floor (round down)',
            ],
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $precision = (int) $this->_getOption($options, 'precision', 0);
        $mode = (string) $this->_getOption($options, 'mode', 'round');

        $floatValue = (float) $value;

        if ($mode === 'ceil') {
            $multiplier = 10 ** $precision;
            return ceil($floatValue * $multiplier) / $multiplier;
        }

        if ($mode === 'floor') {
            $multiplier = 10 ** $precision;
            return floor($floatValue * $multiplier) / $multiplier;
        }

        return round($floatValue, $precision);
    }
}
