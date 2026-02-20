<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form element dependencies mapper
 * Assumes that one element may depend on other element values with the ability to create complex conditions
 */
class Mage_Adminhtml_Block_Widget_Form_Element_Dependence extends Mage_Adminhtml_Block_Abstract
{
    public const MODE_NOT = 'NOT';
    public const MODE_AND = 'AND';
    public const MODE_OR  = 'OR';
    public const MODE_XOR = 'XOR';

    /**
     * Optional field alias to ID map
     *
     * @var array
     */
    protected $_fields = [];

    /**
     * Optional fallback field values for elements that are not present in form
     *
     * @var array
     */
    protected $_fieldValues = [];

    /**
     * Key/value pairs of fields and their conditions to be visible
     *
     * @var array
     */
    protected $_depends = [];

    /**
     * Additional configuration options for the dependencies javascript controller
     *
     * @var array
     */
    protected $_configOptions = [];

    /**
     * Determine if the condition is a logical operator
     */
    public function isLogicalOperator(?string $operator): bool
    {
        $operators = [
            self::MODE_NOT,
            self::MODE_AND,
            self::MODE_OR,
            self::MODE_XOR,
        ];
        return in_array($operator, $operators);
    }

    /**
     * Create a new (sub) condition
     *
     * @see self::addComplexFieldDependence()
     * @param self::MODE_* $operator one of MODE_NOT, MODE_AND, MODE_OR, MODE_XOR
     */
    public function createCondition(string $operator, array $condition): array
    {
        if (!$this->isLogicalOperator($operator)) {
            Mage::throwException($this->__("Invalid operator '%s', must be one of NOT, AND, OR, XOR", $operator));
        }
        return ['operator' => $operator, 'condition' => $condition];
    }

    /**
     * Register field dependency with specified values
     *
     * Note: Calling this method multiple times with the same $targetField will create an AND condition
     *
     * Example 1: `result` field will be shown if `source == 'foo'`
     *
     * $block->addFieldDependence('result', 'source', 'foo');
     *
     * Example 2: `result` field will be shown if `source == 'foo' OR source == 'bar'`
     *
     * $block->addFieldDependence('result', 'source', ['foo', 'bar']);
     *
     * Example 3: `result` field will be shown if `source_1 == 'foo' AND source_2 == 'bar'`
     *
     * $block->addFieldDependence('result', 'source_1', 'foo')
     *       ->addFieldDependence('result', 'source_2', 'bar');
     *
     * @param string $targetField field to be toggled
     * @param string $dependentField field that triggers the display of $targetField
     * @param string|array $refValues wanted value(s) of the $dependentField element
     * @return $this
     */
    public function addFieldDependence($targetField, $dependentField, $refValues)
    {
        if ($this->isLogicalOperator($dependentField)) {
            Mage::throwException($this->__("Invalid field name '%s', must not be one of NOT, AND, OR, XOR", $dependentField));
        }
        $refValues = is_array($refValues) ? $refValues : [$refValues];
        if (isset($this->_depends[$targetField][$dependentField])) {
            $this->_depends[$targetField][$dependentField] = array_unique(
                array_merge($this->_depends[$targetField][$dependentField], $refValues),
            );
        } else {
            $this->_depends[$targetField][$dependentField] = $refValues;
        }
        return $this;
    }

    /**
     * Register field dependency with complex condition
     *
     * Note: Calling this method multiple times with the same $targetField will create an AND condition
     *
     * Example 1: `result` field will be shown if `source_1 == 'foo' AND source_2 == 'bar'`
     *
     * $block->addComplexFieldDependence('result', $block::MODE_AND, [
     *     'source_1' => 'foo',
     *     'source_2' => 'bar',
     * ]);
     *
     * Example 2: `result` field will be shown if `source_1 == 'foo' OR source_2 == 'bar'`
     *
     * $block->addComplexFieldDependence('result', $block::MODE_OR, [
     *     'source_1' => 'foo',
     *     'source_2' => 'bar',
     * ]);
     *
     * Example 3: `result` field will be shown if `source_1 != 'foo' AND source_2 != 'bar'`
     *
     * $block->addComplexFieldDependence('result', $block::MODE_NOT, [
     *     'source_1' => 'foo',
     *     'source_2' => 'bar',
     * ]);
     *
     * Example 4: `result` field will be shown if `source_1 == 'foo' XOR source_2 == 'bar'`
     * If more than two conditions are provided, returns true if exactly one condition is true
     *
     * $block->addComplexFieldDependence('result', $block::MODE_XOR, [
     *     'source_1' => 'foo',
     *     'source_2' => 'bar',
     * ]);
     *
     * Example 5: `result` field will be shown if `(source_1 == 'foo') OR (source_2 == 'bar' AND source_3 == 'baz')`
     *
     * $block->addComplexFieldDependence('result', $block::MODE_OR, [
     *     'source_1' => 'foo',
     *     $block->createCondition($block::MODE_AND, [
     *         'source_2' => 'bar',
     *         'source_3' => 'baz',
     *     ]),
     * ]);
     *
     * @param string $targetField field to be toggled
     * @param self::MODE_* $operator one of MODE_NOT, MODE_AND, MODE_OR, MODE_XOR
     * @return $this
     */
    public function addComplexFieldDependence(string $targetField, string $operator, array $condition): self
    {
        $this->_depends[$targetField][] = $this->createCondition($operator, $condition);
        return $this;
    }

    /**
     * Return a field's full simple or complex field dependence condition
     */
    public function getRawFieldDependence(string $targetField): ?array
    {
        return $this->_depends[$targetField] ?? null;
    }

    /**
     * Set a field's full simple or complex field dependence condition
     */
    public function setRawFieldDependence(string $targetField, array $condition): self
    {
        // Complex conditions must be wrapped in an array
        if ($this->isLogicalOperator($condition['operator'] ?? null) && is_array($condition['condition'] ?? null)) {
            $condition = [$condition];
        }
        $this->_depends[$targetField] = $condition;
        return $this;
    }

    /**
     * Clear a field's full simple or complex field dependence condition
     */
    public function clearFieldDependence(string $targetField): self
    {
        unset($this->_depends[$targetField]);
        return $this;
    }

    /**
     * Add field alias to id mapping
     *
     * @param string $fieldId element ID in DOM
     * @param string $fieldAlias alias to refer to this field by
     * @return $this
     */
    public function addFieldMap($fieldId, $fieldAlias)
    {
        $this->_fields[$fieldAlias] = $fieldId;
        return $this;
    }

    /**
     * Add configuration option to the javascript dependencies controller
     *
     * @see self::addConfigOptions()
     */
    public function addConfigOption(string $option, $value): self
    {
        $this->_configOptions[$option] = $value;
        return $this;
    }

    /**
     * Add multiple configuration options to the javascript dependencies controller
     *
     * @param array $options {
     *     on_event: string,    // the event name that triggers condition evaluation, false to disable, defaults to "change"
     *     field_map: array,    // key/value pairs of field aliases to their associated DOM IDs.
     *     field_values: array, // key/value pairs of fallback values for fields not present in the form
     *     levels_up: int,      // deprecated: the number of ancestor elements to find the parent element to hide
     *     can_edit_price: bool // deprecated: prevent enabling fields, only use if dependence block contains no other elements
     * }
     * @return $this
     */
    public function addConfigOptions(array $options)
    {
        $this->_configOptions = array_merge($this->_configOptions, $options);
        return $this;
    }

    /**
     * Add fallback values for fields not present in the form
     */
    public function addFieldValue(string $field, string $value): self
    {
        $this->_fieldValues[$field] = $value;
        return $this;
    }

    /**
     * Return script block to initialize the dependencies javascript controller
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_depends) {
            return '';
        }
        return "<script>new formElementDependenceController({$this->_getDependsJson()}, {$this->_getConfigJson()})</script>\n";
    }

    /**
     * Field dependencies JSON map generator
     *
     * @return string
     */
    protected function _getDependsJson()
    {
        return Mage::helper('core')->jsonEncode($this->_depends);
    }

    /**
     * Config options with field map JSON map generator
     */
    protected function _getConfigJson(): string
    {
        $config = [
            'field_map' => $this->_fields,
            'field_values' => $this->_fieldValues,
            ...$this->_configOptions,
        ];
        return Mage::helper('core')->jsonEncode($config);
    }
}
