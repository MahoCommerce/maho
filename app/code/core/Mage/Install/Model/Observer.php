<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

class Mage_Install_Model_Observer
{
    #[Maho\Config\Observer('core_locale_set_locale', area: 'install')]
    public function bindLocale(Maho\Event\Observer $observer): self
    {
        if ($locale = $observer->getEvent()->getLocale()) {
            if ($choosedLocale = Mage::getSingleton('install/session')->getLocale()) {
                $locale->setLocaleCode($choosedLocale);
            }
        }
        return $this;
    }

    #[Maho\Config\Observer('mage_run_exception', area: 'install')]
    public function installFailure(Maho\Event\Observer $observer): void
    {
        echo '<h2>There was a problem proceeding with Maho installation.</h2>';
        echo '<p>Please contact developers with error messages on this page.</p>';
        Mage::printException($observer->getEvent()->getException());
    }
}
