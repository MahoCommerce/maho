<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
