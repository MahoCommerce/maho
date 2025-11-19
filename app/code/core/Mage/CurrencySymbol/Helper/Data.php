<?php

/**
 * Maho
 *
 * @package    Mage_CurrencySymbol
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CurrencySymbol_Helper_Data extends Mage_Core_Helper_Data
{
    protected $_moduleName = 'Mage_CurrencySymbol';

    /**
     * Get currency display options
     *
     * @param string $baseCode
     * @return array
     */
    public function getCurrencyOptions($baseCode)
    {
        $currencyOptions = [];
        $currencySymbol = Mage::getModel('currencysymbol/system_currencysymbol');
        if ($currencySymbol) {
            $customCurrencySymbol = $currencySymbol->getCurrencySymbol($baseCode);

            if ($customCurrencySymbol) {
                $currencyOptions['symbol'] = $customCurrencySymbol;
            }
        }

        return $currencyOptions;
    }
}
