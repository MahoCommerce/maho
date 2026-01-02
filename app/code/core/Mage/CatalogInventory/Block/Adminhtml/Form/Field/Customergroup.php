<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogInventory_Block_Adminhtml_Form_Field_Customergroup extends Mage_Core_Block_Html_Select
{
    /**
     * Customer groups cache
     *
     * @var array|null
     */
    private $_customerGroups;

    /**
     * Flag whether to add group all option or no
     *
     * @var bool
     */
    protected $_addGroupAllOption = true;

    /**
     * Retrieve allowed customer groups
     *
     * @param int $groupId  return name by customer group id
     * @return array|string
     */
    protected function _getCustomerGroups($groupId = null)
    {
        if (is_null($this->_customerGroups)) {
            $this->_customerGroups = [];
            $collection = Mage::getModel('customer/group')->getCollection();
            foreach ($collection as $item) {
                /** @var Mage_Customer_Model_Group $item */
                $this->_customerGroups[$item->getId()] = $item->getCustomerGroupCode();
            }
        }
        if (!is_null($groupId)) {
            return $this->_customerGroups[$groupId] ?? null;
        }
        return $this->_customerGroups;
    }

    /**
     * @param string $value
     * @return Mage_CatalogInventory_Block_Adminhtml_Form_Field_Customergroup
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            if ($this->_addGroupAllOption) {
                $this->addOption(Mage_Customer_Model_Group::CUST_GROUP_ALL, Mage::helper('customer')->__('ALL GROUPS'));
            }
            foreach ($this->_getCustomerGroups() as $groupId => $groupLabel) {
                $this->addOption($groupId, addslashes($groupLabel));
            }
        }
        return parent::_toHtml();
    }
}
