<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Model_Login extends Mage_Core_Model_Abstract
{
    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGOUT = 'logout';
    public const TYPE_FAILED = 'failed';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('adminactivitylog/login');
    }

    public function logLogin(Mage_Admin_Model_User $user): self
    {
        if (!Mage::getStoreConfigFlag('adminactivitylog/general/log_login_activity')) {
            return $this;
        }

        $this->setData([
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullname' => $user->getFirstname() . ' ' . $user->getLastname(),
            'type' => self::TYPE_LOGIN,
            'status' => 1,
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'user_agent' => Mage::helper('core/http')->getHttpUserAgent(),
        ]);
        $this->save();

        return $this;
    }

    public function logLogout(Mage_Admin_Model_User $user): self
    {
        if (!Mage::getStoreConfigFlag('adminactivitylog/general/log_login_activity')) {
            return $this;
        }

        $this->setData([
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullname' => $user->getFirstname() . ' ' . $user->getLastname(),
            'type' => self::TYPE_LOGOUT,
            'status' => 1,
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'user_agent' => Mage::helper('core/http')->getHttpUserAgent(),
        ]);
        $this->save();

        return $this;
    }

    public function logFailedLogin(string $username, string $reason = ''): self
    {
        if (!Mage::getStoreConfigFlag('adminactivitylog/general/log_failed_login')) {
            return $this;
        }

        $this->setData([
            'username' => $username,
            'type' => self::TYPE_FAILED,
            'status' => 0,
            'failure_reason' => $reason,
            'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
            'user_agent' => Mage::helper('core/http')->getHttpUserAgent(),
        ]);
        $this->save();

        return $this;
    }

    public function cleanOldLogs(): void
    {
        $daysToKeep = (int) Mage::getStoreConfig('adminactivitylog/general/days_to_keep');
        if ($daysToKeep > 0) {
            $date = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            $this->getCollection()
                ->addFieldToFilter('created_at', ['lt' => $date])
                ->walk('delete');
        }
    }
}
