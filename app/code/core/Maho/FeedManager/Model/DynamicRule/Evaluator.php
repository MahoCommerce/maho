<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Evaluator
 *
 * Evaluates dynamic rules against product data to compute output values.
 */
class Maho_FeedManager_Model_DynamicRule_Evaluator
{
    protected Maho_FeedManager_Model_DynamicRule $_rule;

    /**
     * Supported operators
     */
    protected const OPERATORS = [
        'eq', 'neq', 'gt', 'gteq', 'lt', 'lteq',
        'in', 'nin', 'like', 'nlike', 'null', 'notnull',
        'lt_attr', 'gt_attr', 'eq_attr', 'neq_attr', // Attribute comparison operators
    ];

    public function __construct(Maho_FeedManager_Model_DynamicRule $rule)
    {
        $this->_rule = $rule;
    }

    /**
     * Evaluate the rule against product data
     *
     * @param array $rawData Product data array
     * @param Mage_Catalog_Model_Product|null $product Product model (optional, for complex lookups)
     * @return mixed The computed output value, or null if no row matches
     */
    public function evaluate(array $rawData, ?Mage_Catalog_Model_Product $product = null): mixed
    {
        $outputRows = $this->_rule->getOutputRows();

        foreach ($outputRows as $row) {
            $conditions = $row['conditions'] ?? [];

            // Empty conditions = default/fallback (always matches)
            if (empty($conditions) || $this->_evaluateConditions($conditions, $rawData)) {
                return $this->_getOutputValue($row, $rawData, $product);
            }
        }

        return null;
    }

    /**
     * Evaluate all conditions in a row (AND logic)
     */
    protected function _evaluateConditions(array $conditions, array $rawData): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->_evaluateCondition($condition, $rawData)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function _evaluateCondition(array $condition, array $rawData): bool
    {
        $attribute = $condition['attribute'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $compareValue = $condition['value'] ?? '';

        // Get the attribute value from raw data
        $value = $rawData[$attribute] ?? null;

        // Handle attribute-to-attribute comparison operators
        if (in_array($operator, ['lt_attr', 'gt_attr', 'eq_attr', 'neq_attr'])) {
            $compareValue = $rawData[$compareValue] ?? null;
            // Convert to base operator
            $operator = str_replace('_attr', '', $operator);
        }

        return $this->_compare($value, $operator, $compareValue);
    }

    /**
     * Compare value against operator and compare value
     */
    protected function _compare(mixed $value, string $operator, mixed $compareValue): bool
    {
        // Normalize value for comparison
        $strValue = is_array($value) ? '' : (string) $value;
        $numValue = is_numeric($value) ? (float) $value : null;
        $compareNumValue = is_numeric($compareValue) ? (float) $compareValue : null;

        return match ($operator) {
            'eq' => $strValue === (string) $compareValue,
            'neq' => $strValue !== (string) $compareValue,
            'gt' => $numValue !== null && $compareNumValue !== null && $numValue > $compareNumValue,
            'gteq' => $numValue !== null && $compareNumValue !== null && $numValue >= $compareNumValue,
            'lt' => $numValue !== null && $compareNumValue !== null && $numValue < $compareNumValue,
            'lteq' => $numValue !== null && $compareNumValue !== null && $numValue <= $compareNumValue,
            'in' => $this->_isInList($strValue, (string) $compareValue),
            'nin' => !$this->_isInList($strValue, (string) $compareValue),
            'like' => str_contains(strtolower($strValue), strtolower((string) $compareValue)),
            'nlike' => !str_contains(strtolower($strValue), strtolower((string) $compareValue)),
            'null' => $this->_isEmpty($value),
            'notnull' => !$this->_isEmpty($value),
            default => false,
        };
    }

    /**
     * Check if value is in comma-separated list
     */
    protected function _isInList(string $value, string $list): bool
    {
        $items = array_map('trim', explode(',', $list));
        return in_array($value, $items, true);
    }

    /**
     * Check if value is considered empty
     */
    protected function _isEmpty(mixed $value): bool
    {
        return $value === null
            || $value === ''
            || (is_array($value) && empty($value));
    }

    /**
     * Get the output value for a matched row
     */
    protected function _getOutputValue(array $row, array $rawData, ?Mage_Catalog_Model_Product $product): mixed
    {
        $outputType = $row['output_type'] ?? Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC;
        $outputValue = $row['output_value'] ?? '';
        $outputAttribute = $row['output_attribute'] ?? null;

        return match ($outputType) {
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC => $outputValue,
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_ATTRIBUTE => $this->_getAttributeValue($outputAttribute, $rawData, $product),
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_COMBINED => $outputValue . $this->_getAttributeValue($outputAttribute, $rawData, $product),
            default => null,
        };
    }

    /**
     * Get attribute value from raw data or product
     */
    protected function _getAttributeValue(?string $attribute, array $rawData, ?Mage_Catalog_Model_Product $product): mixed
    {
        if (empty($attribute)) {
            return '';
        }

        // Try raw data first
        if (isset($rawData[$attribute])) {
            return $rawData[$attribute];
        }

        // Fall back to product model if available
        if ($product) {
            return $product->getData($attribute);
        }

        return '';
    }

    /**
     * Get supported operators for UI
     */
    public static function getOperatorOptions(): array
    {
        return [
            'eq' => Mage::helper('feedmanager')->__('Equals'),
            'neq' => Mage::helper('feedmanager')->__('Not Equals'),
            'gt' => Mage::helper('feedmanager')->__('Greater Than'),
            'gteq' => Mage::helper('feedmanager')->__('Greater or Equal'),
            'lt' => Mage::helper('feedmanager')->__('Less Than'),
            'lteq' => Mage::helper('feedmanager')->__('Less or Equal'),
            'in' => Mage::helper('feedmanager')->__('Is One Of'),
            'nin' => Mage::helper('feedmanager')->__('Is Not One Of'),
            'like' => Mage::helper('feedmanager')->__('Contains'),
            'nlike' => Mage::helper('feedmanager')->__('Does Not Contain'),
            'null' => Mage::helper('feedmanager')->__('Is Empty'),
            'notnull' => Mage::helper('feedmanager')->__('Is Not Empty'),
            'lt_attr' => Mage::helper('feedmanager')->__('Less Than (Attribute)'),
            'gt_attr' => Mage::helper('feedmanager')->__('Greater Than (Attribute)'),
        ];
    }
}
