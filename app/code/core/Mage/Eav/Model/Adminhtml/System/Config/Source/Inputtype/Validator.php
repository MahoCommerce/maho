<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator
{
    protected $_messageTemplates;
    protected $_haystack;
    protected $_strict;

    public function __construct()
    {
        //set data haystack
        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');
        $haystack = $helper->getInputTypesValidatorData();

        //reset message template and set custom
        $this->_messageTemplates = [];
        $this->_initMessageTemplates();

        // Store haystack for validation
        $this->_haystack = $haystack;
        $this->_strict = true;
    }

    /**
     * Initialize message templates with translating
     *
     * @return $this
     */
    protected function _initMessageTemplates()
    {
        if (!$this->_messageTemplates) {
            $this->_messageTemplates = [
                self::NOT_IN_ARRAY =>
                    Mage::helper('core')->__('Input type "%value%" not found in the input types list.'),
            ];
        }
        return $this;
    }

    /**
     * Add input type to haystack
     *
     * @param string $type
     * @return $this
     */
    public function addInputType($type)
    {
        if (!in_array((string) $type, $this->_haystack, true)) {
            $this->_haystack[] = (string) $type;
        }
        return $this;
    }

    /**
     * Validate value is in haystack
     *
     * @param mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        return in_array($value, $this->_haystack, $this->_strict);
    }
}
