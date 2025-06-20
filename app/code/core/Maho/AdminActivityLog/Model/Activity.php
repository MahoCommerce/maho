<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
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
        if (!Mage::getStoreConfigFlag('admin/adminactivitylog/enabled')) {
            return $this;
        }

        $adminUser = Mage::getSingleton('admin/session')->getUser();
        if ($adminUser) {
            $data['user_id'] = $adminUser->getId();
            $data['username'] = $adminUser->getUsername();
            $data['fullname'] = $adminUser->getFirstname() . ' ' . $adminUser->getLastname();
        }

        $data['ip_address'] = Mage::helper('core/http')->getRemoteAddr();
        $data['user_agent'] = Mage::helper('core/http')->getHttpUserAgent();
        $data['request_url'] = Mage::helper('core/url')->getCurrentUrl();

        if (isset($data['old_data']) && is_array($data['old_data'])) {
            $data['old_data'] = json_encode($data['old_data']);
        }
        if (isset($data['new_data']) && is_array($data['new_data'])) {
            $data['new_data'] = json_encode($data['new_data']);
        }

        $this->setData($data);
        $this->save();

        return $this;
    }

    public function cleanOldLogs(): void
    {
        $daysToKeep = (int) Mage::getStoreConfig('admin/adminactivitylog/days_to_keep');
        if ($daysToKeep > 0) {
            $date = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            $this->getCollection()
                ->addFieldToFilter('created_at', ['lt' => $date])
                ->walk('delete');
        }
    }
}
