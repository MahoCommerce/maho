<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

        $data['ip_address'] = Mage::helper('core/http')->getRemoteAddr();
        $data['user_agent'] = Mage::helper('core/http')->getHttpUserAgent();
        $data['request_url'] = Mage::helper('core/url')->getCurrentUrl();

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
}
