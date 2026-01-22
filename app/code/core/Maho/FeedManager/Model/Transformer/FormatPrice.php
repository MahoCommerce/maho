<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_FormatPrice extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'format_price';
    protected string $_name = 'Format Price';
    protected string $_description = 'Format numeric value as price with currency';

    protected array $_optionDefinitions = [
        'currency' => [
            'label' => 'Currency Code',
            'type' => 'text',
            'required' => false,
            'note' => 'ISO currency code (e.g., USD, EUR). Uses feed settings if empty.',
        ],
        'decimals' => [
            'label' => 'Decimal Places',
            'type' => 'text',
            'required' => false,
            'note' => 'Number of decimal places. Uses feed settings if empty.',
        ],
        'decimal_separator' => [
            'label' => 'Decimal Separator',
            'type' => 'text',
            'required' => false,
            'note' => 'Character for decimal. Uses feed settings if empty.',
        ],
        'thousands_separator' => [
            'label' => 'Thousands Separator',
            'type' => 'text',
            'required' => false,
            'note' => 'Character for thousands. Uses feed settings if empty.',
        ],
        'skip_if_empty' => [
            'label' => 'Skip if Empty',
            'type' => 'select',
            'required' => false,
            'note' => 'Return empty string if value is null, empty, or zero',
            'options' => [
                ['value' => '0', 'label' => 'No'],
                ['value' => '1', 'label' => 'Yes'],
            ],
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $skipIfEmpty = (bool) $this->_getOption($options, 'skip_if_empty', false);

        // Check if value is empty/null/zero and should be skipped
        if ($skipIfEmpty && ($value === null || $value === '' || (float) $value == 0)) {
            return '';
        }

        if (!is_numeric($value)) {
            return $skipIfEmpty ? '' : $value;
        }

        // Get feed settings as defaults (from Formats & Regional Settings)
        $feedConfig = $productData['_feed'] ?? [];
        $defaultDecimals = $feedConfig['price_decimals'] ?? 2;
        $defaultDecimalSep = $feedConfig['price_decimal_point'] ?? '.';
        $defaultThousandsSep = $feedConfig['price_thousands_sep'] ?? '';
        $defaultCurrency = $feedConfig['price_currency'] ?? '';

        // Use explicit options if provided, otherwise use feed defaults
        $currency = (string) $this->_getOption($options, 'currency', $defaultCurrency);
        $decimals = (int) $this->_getOption($options, 'decimals', $defaultDecimals);
        $decimalSep = (string) $this->_getOption($options, 'decimal_separator', $defaultDecimalSep);
        $thousandsSep = (string) $this->_getOption($options, 'thousands_separator', $defaultThousandsSep);

        $formatted = number_format((float) $value, $decimals, $decimalSep, $thousandsSep);

        if ($currency !== '') {
            $formatted .= ' ' . strtoupper($currency);
        }

        return $formatted;
    }
}
