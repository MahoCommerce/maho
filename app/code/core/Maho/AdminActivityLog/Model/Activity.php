<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Model_Activity extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('adminactivitylog/activity');
    }

    public function logActivity(array $data): self
    {
        if (!Mage::helper('adminactivitylog')->shouldLogActivity()) {
            return $this;
        }

        $adminUser = Mage::getSingleton('admin/session')->getUser();
        if ($adminUser) {
            $data['user_id'] = $adminUser->getId();
            $data['username'] = $adminUser->getUsername();
        }

        $data['ip_address'] ??= Mage::helper('core/http')->getRemoteAddr();
        $data['user_agent'] = Mage::helper('core/http')->getHttpUserAgent();
        $data['request_url'] ??= Mage::helper('core/url')->getCurrentUrl();

        // Preserve action_group_id if provided
        if (isset($data['action_group_id'])) {
            $this->setActionGroupId($data['action_group_id']);
        }

        $helper = Mage::helper('core');
        if (isset($data['old_data']) && is_array($data['old_data'])) {
            $data['old_data'] = $helper->jsonEncode($data['old_data']);
        }
        if (isset($data['new_data']) && is_array($data['new_data'])) {
            $data['new_data'] = $helper->jsonEncode($data['new_data']);
        }

        $this->setData($data);
        $this->save();

        return $this;
    }

    public function getOldData(): array
    {
        $data = $this->getData('old_data');
        if ($data) {
            $helper = Mage::helper('core');
            return $helper->jsonDecode($helper->tryDecrypt($data) ?? $data) ?: [];
        }
        return [];
    }

    public function getNewData(): array
    {
        $data = $this->getData('new_data');
        if ($data) {
            $helper = Mage::helper('core');
            return $helper->jsonDecode($helper->tryDecrypt($data) ?? $data) ?: [];
        }
        return [];
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getId()) {
            $this->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        }

        $helper = Mage::helper('core');
        foreach (['old_data', 'new_data'] as $field) {
            $value = $this->getData($field);
            if ($value !== null && $value !== '') {
                $this->setData($field, $helper->encryptIdempotent($value));
            }
        }

        return parent::_beforeSave();
    }

    /**
     * Get all activities in the same action group
     */
    public function getGroupActivities(): Maho_AdminActivityLog_Model_Resource_Activity_Collection
    {
        $collection = Mage::getResourceModel('adminactivitylog/activity_collection');

        if ($this->getActionGroupId()) {
            $collection->addFieldToFilter('action_group_id', $this->getActionGroupId());
            $collection->setOrder('created_at', 'ASC');
        } else {
            // If no group ID, return collection with just this activity
            $collection->addFieldToFilter('activity_id', $this->getId());
        }

        return $collection;
    }
}
