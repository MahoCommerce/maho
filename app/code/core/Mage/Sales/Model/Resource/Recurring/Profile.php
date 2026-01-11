<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Recurring_Profile extends Mage_Sales_Model_Resource_Abstract
{
    /**
     * Initialize main table and column
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/recurring_profile', 'profile_id');

        $this->_serializableFields = [
            'profile_vendor_info'    => [null, []],
            'additional_info' => [null, []],

            'order_info' => [null, []],
            'order_item_info' => [null, []],
            'billing_address_info' => [null, []],
            'shipping_address_info' => [null, []],
        ];
    }

    /**
     * Unserialize \Maho\DataObject field in an object
     *
     * @param string $field
     * @param mixed $defaultValue
     */
    #[\Override]
    protected function _unserializeField(\Maho\DataObject $object, $field, $defaultValue = null)
    {
        if ($field != 'additional_info') {
            return parent::_unserializeField($object, $field, $defaultValue);
        }
        $value = $object->getData($field);
        if (empty($value)) {
            $object->setData($field, $defaultValue);
        } elseif (!is_array($value) && !is_object($value)) {
            $unserializedValue = false;
            try {
                $unserializedValue = Mage::helper('core/unserializeArray')
                ->unserialize($value);
            } catch (Exception $e) {
                Mage::logException($e);
            }
            $object->setData($field, $unserializedValue);
        }
    }

    /**
     * Return recurring profile child Orders Ids
     *
     * @param \Maho\DataObject $object
     * @return array
     */
    public function getChildOrderIds($object)
    {
        $adapter = $this->_getReadAdapter();
        $bind    = [':profile_id' => $object->getId()];
        $select  = $adapter->select()
            ->from(
                ['main_table' => $this->getTable('sales/recurring_profile_order')],
                ['order_id'],
            )
            ->where('profile_id=:profile_id');

        return $adapter->fetchCol($select, $bind);
    }

    /**
     * Add order relation to recurring profile
     *
     * @param int $recurringProfileId
     * @param int $orderId
     * @return $this
     */
    public function addOrderRelation($recurringProfileId, $orderId)
    {
        $this->_getWriteAdapter()->insert(
            $this->getTable('sales/recurring_profile_order'),
            [
                'profile_id' => $recurringProfileId,
                'order_id'   => $orderId,
            ],
        );
        return $this;
    }
}
