<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Helper_Rss extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * @param string $path
     * @return void
     * @see Mage_Rss_Helper_Data::authAdmin()
     */
    public function authAdmin($path)
    {
        $session = Mage::getSingleton('rss/session');
        if ($session->isAdminLoggedIn()) {
            return;
        }
        [$username, $password] = Mage::helper('core/http')->authValidate();
        $adminSession = Mage::getModel('admin/session');
        $user = $adminSession->login($username, $password);
        if ($user && $user->getId() && $user->getIsActive() == '1' && $adminSession->isAllowed($path)) {
            $session->setAdmin($user);
        } else {
            Mage::helper('core/http')->authFailed();
        }
    }
}
