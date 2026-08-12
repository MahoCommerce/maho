<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Model_Resource_Subscriber extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * DB read connection
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_read;

    /**
     * DB write connection
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_write;

    /**
     * Name of subscriber link DB table
     *
     * @var string
     */
    protected $_subscriberLinkTable;

    /**
     * Name of scope for error messages
     *
     * @var string
     */
    protected $_messagesScope          = 'newsletter/session';

    /**
     * Get tablename from config
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('newsletter/subscriber', 'subscriber_id');
        $this->_subscriberLinkTable = $this->getTable('newsletter/queue_link');
        $this->_read = $this->_getReadAdapter();
        $this->_write = $this->_getWriteAdapter();
    }

    /**
     * Set error messages scope
     *
     * @param string $scope
     */
    public function setMessagesScope($scope)
    {
        $this->_messagesScope = $scope;
    }

    /**
     * Load subscriber from DB by email
     *
     * @param string $subscriberEmail
     * @return array
     */
    public function loadByEmail($subscriberEmail)
    {
        $select = $this->_read->select()
            ->from($this->getMainTable())
            ->where('subscriber_email=:subscriber_email');

        $result = $this->_read->fetchRow($select, ['subscriber_email' => $subscriberEmail]);

        if (!$result) {
            return [];
        }

        return $result;
    }

    /**
     * Load subscriber by customer
     *
     * @return array
     */
    public function loadByCustomer(Mage_Customer_Model_Customer $customer)
    {
        $select = $this->_read->select()
            ->from($this->getMainTable())
            ->where('customer_id=:customer_id');

        $result = $this->_read->fetchRow($select, ['customer_id' => $customer->getId()]);

        if ($result) {
            return $result;
        }

        $select = $this->_read->select()
            ->from($this->getMainTable())
            ->where('subscriber_email=:subscriber_email')
            ->where('store_id=:store_id');

        $result = $this->_read->fetchRow(
            $select,
            ['subscriber_email' => $customer->getEmail(), 'store_id' => $customer->getStoreId()],
        );

        if ($result) {
            return $result;
        }

        return [];
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->hasData('subscriber_status') && $this->_shouldStampStatusChange($object)) {
            $object->setData('change_status_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }

        return parent::_beforeSave($object);
    }

    protected function _shouldStampStatusChange(Mage_Core_Model_Abstract $object): bool
    {
        $current = false;
        if ($object->getId()) {
            $select = $this->_getReadAdapter()->select()
                ->from($this->getMainTable(), ['subscriber_status', 'change_status_at'])
                ->where($this->getIdFieldName() . ' = ?', $object->getId());
            $current = $this->_getReadAdapter()->fetchRow($select);
        }

        if (!$current) {
            return empty($object->getData('change_status_at'));
        }

        // A caller-supplied change_status_at (imports, fixtures) wins over the automatic stamp
        return $object->getData('change_status_at') == $current['change_status_at']
            && (int) $current['subscriber_status'] !== (int) $object->getData('subscriber_status');
    }

    /**
     * Generates random code for subscription confirmation
     *
     * @return string
     */
    protected function _generateRandomCode()
    {
        return Mage::helper('core')->uniqHash();
    }

    /**
     * Updates data when subscriber received
     *
     * @return $this
     */
    public function received(Mage_Newsletter_Model_Subscriber $subscriber, Mage_Newsletter_Model_Queue $queue)
    {
        $this->_write->beginTransaction();
        try {
            $data['letter_sent_at'] = Mage::app()->getLocale()->formatDateForDb('now');
            $this->_write->update($this->_subscriberLinkTable, $data, [
                'subscriber_id = ?' => $subscriber->getId(),
                'queue_id = ?' => $queue->getId(),
            ]);
            $this->_write->commit();
        } catch (Exception) {
            $this->_write->rollBack();
            Mage::throwException(Mage::helper('newsletter')->__('Cannot mark as received subscriber.'));
        }
        return $this;
    }
}
