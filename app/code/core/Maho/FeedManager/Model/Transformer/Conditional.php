<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_Conditional extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'conditional';
    protected string $_name = 'Conditional Value';
    protected string $_description = 'Output different values based on conditions';

    protected array $_optionDefinitions = [
        'condition_field' => [
            'label' => 'Condition Field',
            'type' => 'text',
            'required' => false,
            'note' => 'Product field to check (leave empty to check current value)',
        ],
        'operator' => [
            'label' => 'Operator',
            'type' => 'select',
            'required' => true,
            'options' => [
                'eq' => 'Equals (==)',
                'neq' => 'Not Equals (!=)',
                'gt' => 'Greater Than (>)',
                'gte' => 'Greater Than or Equal (>=)',
                'lt' => 'Less Than (<)',
                'lte' => 'Less Than or Equal (<=)',
                'empty' => 'Is Empty',
                'not_empty' => 'Is Not Empty',
                'contains' => 'Contains',
                'not_contains' => 'Does Not Contain',
                'in' => 'In List',
                'not_in' => 'Not In List',
            ],
        ],
        'compare_value' => [
            'label' => 'Compare Value',
            'type' => 'text',
            'required' => false,
            'note' => 'Value to compare against (for "in" use comma-separated list)',
        ],
        'true_value' => [
            'label' => 'Value if True',
            'type' => 'text',
            'required' => false,
            'note' => 'Output when condition is true (use {{value}} for original)',
        ],
        'false_value' => [
            'label' => 'Value if False',
            'type' => 'text',
            'required' => false,
            'note' => 'Output when condition is false (use {{value}} for original)',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $conditionField = (string) $this->_getOption($options, 'condition_field', '');
        $operator = (string) $this->_getOption($options, 'operator', 'eq');
        $compareValue = (string) $this->_getOption($options, 'compare_value', '');
        $trueValue = (string) $this->_getOption($options, 'true_value', '{{value}}');
        $falseValue = (string) $this->_getOption($options, 'false_value', '{{value}}');

        // Get the value to check
        $checkValue = $conditionField !== '' ? ($productData[$conditionField] ?? null) : $value;

        // Evaluate condition
        $result = $this->_evaluateCondition($checkValue, $operator, $compareValue);

        // Get output value
        $output = $result ? $trueValue : $falseValue;

        // Replace {{value}} placeholder
        return str_replace('{{value}}', (string) $value, $output);
    }

    /**
     * Evaluate a condition
     */
    protected function _evaluateCondition(mixed $value, string $operator, string $compareValue): bool
    {
        return match ($operator) {
            'eq' => (string) $value === $compareValue,
            'neq' => (string) $value !== $compareValue,
            'gt' => is_numeric($value) && (float) $value > (float) $compareValue,
            'gte' => is_numeric($value) && (float) $value >= (float) $compareValue,
            'lt' => is_numeric($value) && (float) $value < (float) $compareValue,
            'lte' => is_numeric($value) && (float) $value <= (float) $compareValue,
            'empty' => $value === null || $value === '' || (is_array($value) && empty($value)),
            'not_empty' => $value !== null && $value !== '' && !(is_array($value) && empty($value)),
            'contains' => is_string($value) && str_contains($value, $compareValue),
            'not_contains' => !is_string($value) || !str_contains($value, $compareValue),
            'in' => in_array((string) $value, array_map('trim', explode(',', $compareValue))),
            'not_in' => !in_array((string) $value, array_map('trim', explode(',', $compareValue))),
            default => false,
        };
    }
}
