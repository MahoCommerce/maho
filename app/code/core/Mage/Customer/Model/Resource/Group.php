<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer group resource model
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Resource_Group extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Store data from customer_group table
     */
    protected static array $_groupTableData = [];

    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/customer_group', 'customer_group_id');
    }

    /**
     * Initialize unique fields
     *
     * @return $this
     */
    #[\Override]
    protected function _initUniqueFields()
    {
        $this->_uniqueFields = [
            [
                'field' => 'customer_group_code',
                'title' => Mage::helper('customer')->__('Customer Group')
            ]];

        return $this;
    }

    /**
     * Set default attribute set ids
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $group)
    {
        parent::_afterLoad($group);
        if (!$group->hasData('customer_attribute_set_id')) {
            $group->setData('customer_attribute_set_id', Mage_Customer_Model_Group::DEFAULT_ATTRIBUTE_SET_ID);
        }
        if (!$group->hasData('customer_address_attribute_set_id')) {
            $group->setData('customer_address_attribute_set_id', Mage_Customer_Model_Group::DEFAULT_ADDRESS_ATTRIBUTE_SET_ID);
        }
        return $this;
    }

    /**
     * Check if group uses as default
     *
     * @throws Mage_Core_Exception
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _beforeDelete(Mage_Core_Model_Abstract $group)
    {
        /** @var Mage_Customer_Model_Group $group */
        if ($group->usesAsDefault()) {
            Mage::throwException(Mage::helper('customer')->__('The group "%s" cannot be deleted', $group->getCode()));
        }
        return parent::_beforeDelete($group);
    }

    /**
     * Method set default group id to the customers collection
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $group)
    {
        $customerCollection = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToFilter('group_id', $group->getId())
            ->load();
        /** @var Mage_Customer_Model_Customer $customer */
        foreach ($customerCollection as $customer) {
            $defaultGroupId = Mage::helper('customer')->getDefaultCustomerGroupId($customer->getStoreId());
            $customer->setGroupId($defaultGroupId);
            $customer->save();
        }
        return parent::_afterDelete($group);
    }

    /**
     * Load group and store data in static property
     */
    public static function loadGroupTableData(int $groupId): array
    {
        if (empty(self::$_groupTableData[$groupId])) {
            self::$_groupTableData[$groupId] = Mage::getModel('customer/group')->load($groupId)->getData();
        }
        return self::$_groupTableData[$groupId];
    }
}
