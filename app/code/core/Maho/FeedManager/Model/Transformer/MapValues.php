<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_MapValues extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'map_values';
    protected string $_name = 'Map Values';
    protected string $_description = 'Map input values to different output values (e.g., "1" → "in_stock")';

    protected array $_optionDefinitions = [
        'mapping' => [
            'label' => 'Value Mapping',
            'type' => 'textarea',
            'required' => true,
            'note' => 'One mapping per line in format: input_value=output_value',
        ],
        'default' => [
            'label' => 'Default Output',
            'type' => 'text',
            'required' => false,
            'note' => 'Value to use if no mapping matches (leave empty to keep original)',
        ],
        'case_sensitive' => [
            'label' => 'Case Sensitive',
            'type' => 'select',
            'required' => false,
            'options' => ['0' => 'No', '1' => 'Yes'],
            'note' => 'Match input case exactly',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $mappingText = (string) $this->_getOption($options, 'mapping', '');
        $default = $this->_getOption($options, 'default');
        $caseSensitive = (bool) $this->_getOption($options, 'case_sensitive', false);

        $mapping = $this->_parseMappingText($mappingText, $caseSensitive);
        $lookupValue = $caseSensitive ? (string) $value : strtolower((string) $value);

        return $mapping[$lookupValue] ?? $default ?? $value;
    }

    /**
     * Parse mapping text into array
     */
    protected function _parseMappingText(string $text, bool $caseSensitive): array
    {
        $mapping = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$input, $output] = explode('=', $line, 2);
            $input = trim($input);
            $output = trim($output);

            if (!$caseSensitive) {
                $input = strtolower($input);
            }

            $mapping[$input] = $output;
        }

        return $mapping;
    }
}
