<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Eav_Model_Entity_Attribute_Frontend_Abstract implements Mage_Eav_Model_Entity_Attribute_Frontend_Interface
{
    /**
     * Reference to the attribute instance
     *
     * @var Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected $_attribute;

    /**
     * Set attribute instance
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
     */
    public function setAttribute($attribute)
    {
        $this->_attribute = $attribute;
        return $this;
    }

    /**
     * Get attribute instance
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute()
    {
        return $this->_attribute;
    }

    /**
     * Get attribute type for user interface form
     *
     * @return string
     */
    public function getInputType()
    {
        return $this->getAttribute()->getFrontendInput();
    }

    /**
     * Retrieve label
     *
     * @return string
     */
    public function getLabel()
    {
        $label = $this->getAttribute()->getFrontendLabel();
        if (($label === null) || $label == '') {
            $label = $this->getAttribute()->getAttributeCode();
        }

        return $label;
    }

    /**
     * Retrieve attribute value
     *
     * @return mixed
     */
    public function getValue(Varien_Object $object)
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());
        if (in_array($this->getConfigField('input'), ['select','boolean'])) {
            $valueOption = $this->getOption($value);
            if (!$valueOption) {
                $opt     = Mage::getModel('eav/entity_attribute_source_boolean');
                $options = $opt->getAllOptions();
                if ($options) {
                    foreach ($options as $option) {
                        if ($option['value'] == $value) {
                            $valueOption = $option['label'];
                        }
                    }
                }
            }
            $value = $valueOption;
        } elseif ($this->getConfigField('input') == 'multiselect') {
            $value = $this->getOption($value);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
        }

        return $value;
    }

    /**
     * Checks if attribute is visible on frontend
     *
     * @return bool
     */
    public function isVisible()
    {
        return $this->getConfigField('frontend_visible');
    }

    /**
     * Retrieve frontend class
     *
     * @return string
     */
    public function getClass()
    {
        $out    = [];

        $frontendClass = $this->getAttribute()->getFrontendClass();
        if ($frontendClass) {
            $out[]  = $frontendClass;
        }

        if ($this->getAttribute()->getIsRequired()) {
            $out[]  = 'required-entry';
        }

        $inputRuleClass = $this->_getInputValidateClass();
        if ($inputRuleClass) {
            $out[] = $inputRuleClass;
        }

        if (!empty($out)) {
            $out = implode(' ', $out);
        } else {
            $out = '';
        }
        return $out;
    }

    /**
     * Return validate class by attribute input validation rule
     *
     * @return string|false
     */
    protected function _getInputValidateClass()
    {
        $class          = false;
        $validateRules  = $this->getAttribute()->getValidateRules();
        if (!empty($validateRules['input_validation'])) {
            switch ($validateRules['input_validation']) {
                case 'alphanumeric':
                    $class = 'validate-alphanum';
                    break;
                case 'numeric':
                    $class = 'validate-digits';
                    break;
                case 'alpha':
                    $class = 'validate-alpha';
                    break;
                case 'email':
                    $class = 'validate-email';
                    break;
                case 'url':
                    $class = 'validate-url';
                    break;
                default:
                    break;
            }
        }
        return $class;
    }

    /**
     * Reireive config field
     *
     * @param string $fieldName
     * @return mixed
     */
    public function getConfigField($fieldName)
    {
        return $this->getAttribute()->getData('frontend_' . $fieldName);
    }

    /**
     * Get select options in case it's select box and options source is defined
     *
     * @return array
     */
    public function getSelectOptions()
    {
        return $this->getAttribute()->getSource()->getAllOptions();
    }

    /**
     * Retrieve option by option id
     *
     * @param int $optionId
     * @return string|bool
     */
    public function getOption($optionId)
    {
        $source = $this->getAttribute()->getSource();
        if ($source) {
            return $source->getOptionText($optionId);
        }
        return false;
    }

    /**
     * Retrieve Input Renderer Class
     *
     * @return string|null
     */
    public function getInputRendererClass()
    {
        $className = $this->getAttribute()->getData('frontend_input_renderer');
        if ($className) {
            return Mage::getConfig()->getBlockClassName($className);
        }
        return null;
    }
}
