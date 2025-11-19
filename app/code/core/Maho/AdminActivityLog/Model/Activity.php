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

        $encryption = Mage::getModel('core/encryption');

        if (isset($data['old_data']) && is_array($data['old_data'])) {
            $jsonData = json_encode($data['old_data']);
            $data['old_data'] = $encryption->encrypt($jsonData);
        }
        if (isset($data['new_data']) && is_array($data['new_data'])) {
            $jsonData = json_encode($data['new_data']);
            $data['new_data'] = $encryption->encrypt($jsonData);
        }

        $this->setData($data);
        $this->save();

        return $this;
    }

    public function getOldData(): array
    {
        $data = $this->getData('old_data');
        if ($data) {
            $decrypted = Mage::getModel('core/encryption')->decrypt($data);
            if ($decrypted && json_validate($decrypted)) {
                return json_decode($decrypted, true);
            }
        }
        return [];
    }

    public function getNewData(): array
    {
        $data = $this->getData('new_data');
        if ($data) {
            $decrypted = Mage::getModel('core/encryption')->decrypt($data);
            if ($decrypted && json_validate($decrypted)) {
                return json_decode($decrypted, true);
            }
        }
        return [];
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getId()) {
            $this->setCreatedAt(Mage::getModel('core/date')->gmtDate());
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
