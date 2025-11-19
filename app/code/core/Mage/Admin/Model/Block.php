<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Admin_Model_Resource_Block _getResource()
 * @method Mage_Admin_Model_Resource_Block getResource()
 * @method Mage_Admin_Model_Resource_Block_Collection getCollection()
 *
 * @method string getBlockName()
 * @method string getIsAllowed()
 */
class Mage_Admin_Model_Block extends Mage_Core_Model_Abstract
{
    /**
     * Initialize variable model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/block');
    }

    /**
     * @return array|true
     */
    public function validate()
    {
        $errors = [];

        if (!Mage::helper('core')->isValidNotBlank($this->getBlockName())) {
            $errors[] = Mage::helper('adminhtml')->__('Block Name is required field.');
        }

        $disallowedBlockNames = Mage::helper('admin/block')->getDisallowedBlockNames();
        if (in_array($this->getBlockName(), $disallowedBlockNames)) {
            $errors[] = Mage::helper('adminhtml')->__('Block Name is disallowed.');
        }

        if (!Mage::helper('core')->isValidRegex($this->getBlockName(), '/^[-_a-zA-Z0-9]+\/[-_a-zA-Z0-9\/]+$/')) {
            $errors[] = Mage::helper('adminhtml')->__('Block Name is incorrect.');
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
     * Check is block with such type allowed for parsing via blockDirective method
     *
     * @param string $type
     * @return bool
     */
    public function isTypeAllowed($type)
    {
        return Mage::helper('admin/block')->isTypeAllowed($type);
    }
}
