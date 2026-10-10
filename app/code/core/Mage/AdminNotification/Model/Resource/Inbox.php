<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

class Mage_AdminNotification_Model_Resource_Inbox extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * AdminNotification Resource initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('adminnotification/inbox', 'notification_id');
    }

    /**
     * Load latest notice
     *
     * @return $this
     */
    public function loadLatestNotice(Mage_AdminNotification_Model_Inbox $object)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->order($this->getIdFieldName() . ' DESC')
            ->where('is_read != 1')
            ->where('is_remove != 1')
            ->limit(1);
        $this->addAudienceFilter($select);
        $data = $adapter->fetchRow($select);

        if ($data) {
            $object->setData($data);
        }

        $this->_afterLoad($object);

        return $this;
    }

    /**
     * Get notifications grouped by severity
     *
     * @return array
     */
    public function getNoticeStatus(Mage_AdminNotification_Model_Inbox $object)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), [
                'severity'     => 'severity',
                'count_notice' => new Maho\Db\Expr('COUNT(' . $this->getIdFieldName() . ')')])
            ->group('severity')
            ->where('is_remove=?', 0)
            ->where('is_read=?', 0);
        $this->addAudienceFilter($select);
        return $adapter->fetchPairs($select);
    }

    /**
     * Keep the notifications the current administrator may see: the ones for everyone, the
     * ones for this administrator, and the ones for an ACL resource this administrator has.
     */
    public function addAudienceFilter(\Maho\Db\Select $select): \Maho\Db\Select
    {
        $session = Mage::getSingleton('admin/session');
        $select->where('admin_user_id IS NULL OR admin_user_id = ?', (int) $session->getUser()?->getId());

        $adapter = $this->_getReadAdapter();
        $resources = $adapter->fetchCol(
            $adapter->select()
                ->distinct()
                ->from($this->getMainTable(), ['acl_resource'])
                ->where('acl_resource IS NOT NULL'),
        );
        $allowed = array_values(array_filter($resources, fn($resource) => $session->isAllowed((string) $resource)));
        if ($allowed === []) {
            $select->where('acl_resource IS NULL');
        } else {
            $select->where('acl_resource IS NULL OR acl_resource IN (?)', $allowed);
        }

        return $select;
    }

    /**
     * Save notifications (if not exists)
     */
    public function parse(Mage_AdminNotification_Model_Inbox $object, array $data)
    {
        $adapter = $this->_getWriteAdapter();
        foreach ($data as $item) {
            $select = $adapter->select()
                ->from($this->getMainTable())
                ->where('title = ?', $item['title']);

            if (empty($item['url'])) {
                $select->where('url IS NULL');
            } else {
                $select->where('url = ?', $item['url']);
            }

            if (isset($item['internal'])) {
                $row = false;
                unset($item['internal']);
            } else {
                $row = $adapter->fetchRow($select);
            }

            if (!$row) {
                $adapter->insert($this->getMainTable(), $item);
            }
        }
    }
}
