<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Persistent
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Persistent Session Resource Model
 *
 * @category   Mage
 * @package    Mage_Persistent
 */
class Mage_Persistent_Model_Resource_Session extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Use is object new method for object saving
     *
     * @var bool
     */
    protected $_useIsObjectNew = true;

    #[\Override]
    protected function _construct()
    {
        $this->_init('persistent/session', 'persistent_id');
    }

    /**
     * Add expiration date filter to select
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Persistent_Model_Session $object
     * @return Zend_Db_Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if (!$object->getLoadExpired()) {
            $tableName = $this->getMainTable();
            $select->join(
                ['customer' => $this->getTable('customer/entity')],
                'customer.entity_id = ' . $tableName . '.customer_id'
            )->where($tableName . '.updated_at >= ?', $object->getExpiredBefore());
        }

        return $select;
    }

    /**
     * Delete customer persistent session by customer id
     *
     * @param int $customerId
     * @return $this
     */
    public function deleteByCustomerId($customerId)
    {
        $this->_getWriteAdapter()->delete($this->getMainTable(), ['customer_id = ?' => $customerId]);
        return $this;
    }

    /**
     * Check if such session key allowed (not exists)
     *
     * @param string $key
     * @return bool
     */
    public function isKeyAllowed($key)
    {
        $sameSession = Mage::getModel('persistent/session')->setLoadExpired();
        $sameSession->loadByCookieKey($key);
        return !$sameSession->getId();
    }

    /**
     * Delete expired persistent sessions
     *
     * @param  int $websiteId
     * @param  string $expiredBefore
     * @return $this
     */
    public function deleteExpired($websiteId, $expiredBefore)
    {
        $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            [
                'website_id = ?' => $websiteId,
                'updated_at < ?' => $expiredBefore,
            ]
        );
        return $this;
    }
}