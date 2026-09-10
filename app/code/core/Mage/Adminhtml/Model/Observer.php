<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_Observer
{
    /**
     * Tell the admin which markup the save sanitizer removed. The sanitizer is silent.
     *
     * The resource model records the removals on the object. This reports every save path,
     * so a new one needs no call of its own.
     */
    #[Maho\Config\Observer('model_save_after', area: 'adminhtml')]
    public function displayRemovedHtml(\Maho\Event\Observer $observer): void
    {
        $removed = $observer->getEvent()->getObject()?->getData('removed_html');
        if (!is_array($removed) || $removed === []) {
            return;
        }

        Mage::getSingleton('adminhtml/session')->addNotice(
            Mage::helper('adminhtml')->__('Maho removed HTML that a content field does not allow: %s.', implode(', ', $removed)),
        );
    }

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

    #[Maho\Config\Observer('adminhtml_controller_action_predispatch_start', id: 'store')]
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
    #[Maho\Config\Observer('adminhtml_controller_action_predispatch_start', id: 'massaction')]
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
            // Admin scope, the same the stored record resolves at, so the cookie cannot outlive it
            $lifetime = Mage_Core_Model_Session_Abstract::resolveConfiguredSessionLifetime(
                Mage_Adminhtml_Controller_Action::SESSION_NAMESPACE,
                store: Mage_Core_Model_Store::ADMIN_CODE,
            );

            /** @var Mage_Core_Model_Cookie $cookie */
            $cookie = $observer->getCookie();
            $cookie->setLifetime($lifetime);
        }
    }
}
