<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Admin_Model_Resource_Variable _getResource()
 * @method Mage_Admin_Model_Resource_Variable getResource()
 * @method Mage_Admin_Model_Resource_Variable_Collection getCollection()
 *
 * @method string getIsAllowed()
 * @method string getVariableName()
 */
class Mage_Admin_Model_Variable extends Mage_Core_Model_Abstract
{
    /**
     * Initialize variable model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/variable');
    }

    /**
     * @return array|bool
     * @throws Exception
     * @throws Zend_Validate_Exception
     */
    public function validate()
    {
        $errors = [];

        if (!Zend_Validate::is($this->getVariableName(), 'NotEmpty')) {
            $errors[] = Mage::helper('adminhtml')->__('Variable Name is required field.');
        }
        if (!Zend_Validate::is($this->getVariableName(), 'Regex', ['/^[-_a-zA-Z0-9\/]*$/'])) {
            $errors[] = Mage::helper('adminhtml')->__('Variable Name is incorrect.');
        }

        if (!in_array($this->getIsAllowed(), ['0', '1'])) {
            $errors[] = Mage::helper('adminhtml')->__('Is Allowed is required field.');
        }

        if (empty($errors)) {
            return true;
        }
        return $errors;
    }

    /**
     * Check is config directive with given path can be parsed via configDirective method
     *
     * @param string $path
     * @return bool
     */
    public function isPathAllowed($path)
    {
        return Mage::helper('admin/variable')->isPathAllowed($path);
    }
}
