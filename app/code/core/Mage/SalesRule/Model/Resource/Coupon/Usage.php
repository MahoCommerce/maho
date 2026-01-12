<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_SalesRule_Model_Resource_Coupon_Usage extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('salesrule/coupon_usage', '');
    }

    /**
     * Increment times_used counter
     *
     * @param int $customerId
     * @param int $couponId
     * @param bool $decrement   Decrement instead of increment times_used
     */
    public function updateCustomerCouponTimesUsed($customerId, $couponId, $decrement = false)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select();
        $select->from($this->getMainTable(), ['times_used'])
                ->where('coupon_id = :coupon_id')
                ->where('customer_id = :customer_id');

        $timesUsed = $read->fetchOne($select, [':coupon_id' => $couponId, ':customer_id' => $customerId]);

        if ($timesUsed !== false) {
            $timesUsed = (int) $timesUsed + ($decrement ? -1 : 1);
            if ($timesUsed >= 0) {
                $this->_getWriteAdapter()->update(
                    $this->getMainTable(),
                    [
                        'times_used' => $timesUsed,
                    ],
                    [
                        'coupon_id = ?' => $couponId,
                        'customer_id = ?' => $customerId,
                    ],
                );
            }
        } else {
            $this->_getWriteAdapter()->insert(
                $this->getMainTable(),
                [
                    'coupon_id' => $couponId,
                    'customer_id' => $customerId,
                    'times_used' => 1,
                ],
            );
        }
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
