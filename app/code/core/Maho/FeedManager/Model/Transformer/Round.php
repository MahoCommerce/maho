<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
