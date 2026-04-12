<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_Observer
{
    #[Maho\Config\Observer('controller_action_layout_generate_blocks_before', area: 'adminhtml')]
    public function displayBootupWarnings($observer)
    {
        $bootupWarnings = Mage::registry('bootup_warnings') ?? [];
        foreach ($bootupWarnings as $message) {
            Mage::getSingleton('adminhtml/session')->addWarning("Bootup warning: $message");
        }
    }

    #[Maho\Config\Observer('core_locale_set_locale', area: 'adminhtml')]
    public function bindLocale($observer)
    {
        if ($locale = $observer->getEvent()->getLocale()) {
            if ($choosedLocale = Mage::getSingleton('adminhtml/session')->getLocale()) {
                $locale->setLocaleCode($choosedLocale);
            }
        }
        return $this;
    }

    #[Maho\Config\Observer('adminhtml_controller_action_predispatch_start', name: 'store')]
    public function bindStore()
    {
        Mage::app()->setCurrentStore('admin');
        return $this;
    }

    /**
     * Prepare massaction separated data
     *
     * @return $this
     */
    #[Maho\Config\Observer('adminhtml_controller_action_predispatch_start', name: 'massaction')]
    public function massactionPrepareKey()
    {
        $request = Mage::app()->getFrontController()->getRequest();
        if ($key = $request->getPost('massaction_prepare_key')) {
            $value = is_array($request->getPost($key)) ? $request->getPost($key) : explode(',', $request->getPost($key));
            $request->setPost($key, $value ?: null);
        }
        return $this;
    }

    /**
     * Set the admin's session lifetime based on config
     */
    #[Maho\Config\Observer('session_before_renew_cookie')]
    public function setCookieLifetime(\Maho\Event\Observer $observer): void
    {
        if ($observer->getSessionName() === Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE) {
            /** @var Mage_Adminhtml_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');

            $lifetime = Mage::getStoreConfigAsInt('admin/security/session_cookie_lifetime');
            $lifetime = min($lifetime, Mage_Adminhtml_Controller_Action::SESSION_MAX_LIFETIME);
            $lifetime = max($lifetime, Mage_Adminhtml_Controller_Action::SESSION_MIN_LIFETIME);

            /** @var Mage_Core_Model_Cookie $cookie */
            $cookie = $observer->getCookie();
            $cookie->setLifetime($lifetime);
        }
    }
}
