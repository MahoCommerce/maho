<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form element dependencies mapper
 * Assumes that one element may depend on other element values with the ability to create complex conditions
 *
 * @category   Mage
 * @package    Mage_Adminhtml
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
     * Register field dependency with specified values
     *
     * Note: Calling this method multiple times with the same $dependentField value will overwrite the previous condition
     *
     * Example 1: `field_a` will be shown if `field_b == 'foo'`
     *
     * $block->addFieldDependence('field_a', 'field_b', 'foo');
     *
     * Example 2: `field_a` will be shown if `field_b == 'foo' OR field_b == 'bar'`
     *
     * $block->addFieldDependence('field_a', 'field_b', ['foo', 'bar']);
     *
     * Example 3: `field_a` will be shown if `field_b == 'foo' AND field_c == 'bar'`
     *
     * $block->addFieldDependence('field_a', 'field_b', 'foo')
     *       ->addFieldDependence('field_a', 'field_c', 'bar');
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
        $this->_depends[$targetField][$dependentField] = $refValues;
        return $this;
    }

    /**
     * Register field dependency with complex condition
     *
     * Note: Calling this method multiple times with the same $operator value will overwrite the previous condition
     *
     * Example 1: `field_a` will be shown if `field_b == 'foo' AND field_c == 'bar'`
     *
     * $block->addComplexFieldDependence('field_a', $block::MODE_AND, [
     *     'field_b' => 'foo',
     *     'field_c' => 'bar',
     * ]);
     *
     * Example 2: `field_a` will be shown if `field_b == 'foo' OR field_c == 'bar'`
     *
     * $block->addComplexFieldDependence('field_a', $block::MODE_OR, [
     *     'field_b' => 'foo',
     *     'field_c' => 'bar',
     * ]);
     *
     * Example 3: `field_a` will be shown if `field_b != 'foo' AND field_c != 'bar'`
     *
     * $block->addComplexFieldDependence('field_a', $block::MODE_NOT, [
     *     'field_b' => 'foo',
     *     'field_c' => 'bar',
     * ]);
     *
     * Example 4: `field_a` will be shown if `field_b == 'foo' XOR field_c == 'bar'`
     * If more than two conditions are provided, returns true if exactly one condition is true
     *
     * $block->addComplexFieldDependence('field_a', $block::MODE_XOR, [
     *     'field_b' => 'foo',
     *     'field_c' => 'bar',
     * ]);
     *
     * Example 5: `field_a` will be shown if `field_b == 'foo' AND (field_c == 'bar' OR field_d == 'baz')`
     *
     * $block->addComplexFieldDependence('field_a', $block::MODE_AND, [
     *     'field_b' => 'foo',
     *     $block::MODE_OR => [
     *         'field_c' => 'bar',
     *         'field_d' => 'baz',
     *     ],
     * ]);
     *
     * @param string $targetField field to be toggled
     * @param string $operator one of NOT, AND, OR
     * @param array $condition
     * @return $this
     */
    public function addComplexFieldDependence(string $targetField, string $operator, array $condition): self
    {
        if (!$this->isLogicalOperator($operator)) {
            Mage::throwException($this->__("Invalid operator '%s', must be one of NOT, AND, OR, XOR", $operator));
        }
        $this->_depends[$targetField][$operator] = $condition;
        return $this;
    }

    /**
     * Add misc configuration options to the javascript dependencies controller
     *
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
        return "<script>new FormElementDependenceController({$this->_getDependsJson()}, {$this->_getConfigJson()})</script>";
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
     *
     * @return string
     */
    protected function _getConfigJson()
    {
        $this->_configOptions['field_map'] = $this->_fields;
        $this->_configOptions['field_values'] = $this->_fieldValues;
        return Mage::helper('core')->jsonEncode($this->_configOptions);
    }
}
