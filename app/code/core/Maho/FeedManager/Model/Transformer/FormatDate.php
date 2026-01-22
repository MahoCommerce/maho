<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_FormatDate extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'format_date';
    protected string $_name = 'Format Date';
    protected string $_description = 'Format date/time values';

    protected array $_optionDefinitions = [
        'output_format' => [
            'label' => 'Output Format',
            'type' => 'text',
            'required' => true,
            'note' => 'PHP date format (e.g., Y-m-d, c for ISO 8601)',
        ],
        'input_format' => [
            'label' => 'Input Format',
            'type' => 'text',
            'required' => false,
            'note' => 'Expected input format (leave empty for auto-detection)',
        ],
        'timezone' => [
            'label' => 'Output Timezone',
            'type' => 'text',
            'required' => false,
            'note' => 'Timezone for output (e.g., Australia/Sydney, UTC)',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (empty($value)) {
            return $value;
        }

        $outputFormat = (string) $this->_getOption($options, 'output_format', 'Y-m-d');
        $inputFormat = (string) $this->_getOption($options, 'input_format', '');
        $timezone = (string) $this->_getOption($options, 'timezone', '');

        try {
            if ($inputFormat) {
                $date = DateTime::createFromFormat($inputFormat, (string) $value);
                if ($date === false) {
                    return $value;
                }
            } else {
                $date = new DateTime((string) $value);
            }

            if ($timezone) {
                $date->setTimezone(new DateTimeZone($timezone));
            }

            return $date->format($outputFormat);
        } catch (Exception $e) {
            // Return original value if parsing fails
            return $value;
        }
    }
}
