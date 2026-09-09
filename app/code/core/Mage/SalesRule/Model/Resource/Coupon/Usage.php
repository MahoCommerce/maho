<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

class Mage_SalesRule_Model_Resource_Coupon_Usage extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('salesrule/coupon_usage', '');
    }

    /**
     * Increment or decrement the times_used counter
     *
     * @param int $customerId
     * @param int $couponId
     * @param bool $decrement   Decrement instead of increment times_used
     */
    public function updateCustomerCouponTimesUsed($customerId, $couponId, $decrement = false)
    {
        if ($decrement) {
            $this->decrementCustomerTimesUsed((int) $customerId, (int) $couponId);
        } else {
            $this->incrementCustomerTimesUsed((int) $customerId, (int) $couponId);
        }
    }

    /**
     * Count one more use of the coupon by the customer, refusing it once the
     * per-customer limit is reached. The row is created with an idempotent
     * insert and the counter moves in one conditional UPDATE, so concurrent
     * order placements cannot both pass the limit.
     *
     * @param int $usagePerCustomer 0 for unlimited
     * @throws Mage_Core_Exception when the limit is reached
     */
    public function incrementCustomerTimesUsed(int $customerId, int $couponId, int $usagePerCustomer = 0): void
    {
        $adapter = $this->_getWriteAdapter();
        $where = [
            'coupon_id = ?' => $couponId,
            'customer_id = ?' => $customerId,
        ];
        if ($usagePerCustomer > 0) {
            $where['times_used < ?'] = $usagePerCustomer;
        }
        $bind = ['times_used' => new Maho\Db\Expr('times_used + 1')];

        if ($adapter->update($this->getMainTable(), $bind, $where) > 0) {
            return;
        }

        $adapter->insertIgnore($this->getMainTable(), [
            'coupon_id' => $couponId,
            'customer_id' => $customerId,
            'times_used' => 0,
        ]);

        if ($adapter->update($this->getMainTable(), $bind, $where) === 0) {
            Mage::throwException(Mage::helper('salesrule')->__('You have reached the usage limit for this coupon code.'));
        }
    }

    public function decrementCustomerTimesUsed(int $customerId, int $couponId): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['times_used' => new Maho\Db\Expr('times_used - 1')],
            [
                'coupon_id = ?' => $couponId,
                'customer_id = ?' => $customerId,
                'times_used > 0',
            ],
        );
    }

    /**
     * Load an object by customer_id & coupon_id
     *
     * @param int $customerId
     * @param int $couponId
     * @return $this
     */
    public function loadByCustomerCoupon(\Maho\DataObject $object, $customerId, $couponId)
    {
        $read = $this->_getReadAdapter();
        if ($read && $couponId && $customerId) {
            $select = $read->select()
                ->from($this->getMainTable())
                ->where('customer_id =:customer_id')
                ->where('coupon_id = :coupon_id');
            $data = $read->fetchRow($select, [':coupon_id' => $couponId, ':customer_id' => $customerId]);
            if ($data) {
                $object->setData($data);
            }
        }
        if ($object instanceof Mage_Core_Model_Abstract) {
            $this->_afterLoad($object);
        }
        return $this;
    }
}
