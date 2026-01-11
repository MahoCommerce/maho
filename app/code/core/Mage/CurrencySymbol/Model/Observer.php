<?php

/**
 * Maho
 *
 * @package    Mage_CurrencySymbol
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CurrencySymbol_Model_Observer
{
    /**
     * Generate options for currency displaying with custom currency symbol
     *
     * @return $this
     */
    public function currencyDisplayOptions(\Maho\Event\Observer $observer)
    {
        $baseCode = $observer->getEvent()->getBaseCode();
        $currencyOptions = $observer->getEvent()->getCurrencyOptions();
        $currencyOptions->setData(Mage::helper('currencysymbol')->getCurrencyOptions($baseCode));

        return $this;
    }
}
