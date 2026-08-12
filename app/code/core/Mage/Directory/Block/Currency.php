<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Block_Currency extends Mage_Core_Block_Template
{
    /**
     * Retrieve count of currencies
     * Return 0 if only one currency
     *
     * @return int
     */
    public function getCurrencyCount()
    {
        return count($this->getCurrencies());
    }

    /**
     * Retrieve currencies array
     * Return array: code => currency name
     * Return empty array if only one currency
     *
     * @return array
     */
    public function getCurrencies()
    {
        $currencies = $this->getData('currencies');
        if (is_null($currencies)) {
            $currencies = [];
            $codes = array_keys(Mage::app()->getStore()->getServeableCurrencyRates());
            if (count($codes) > 1) {
                foreach ($codes as $code) {
                    $currencies[$code] = Mage::app()->getLocale()
                        ->getTranslation($code, 'nametocurrency');
                }
            }

            $this->setData('currencies', $currencies);
        }
        return $currencies;
    }

    /**
     * Retrieve Currency Switch URL
     *
     * @return string
     */
    public function getSwitchUrl()
    {
        return $this->getUrl('directory/currency/switch');
    }

    /**
     * Return URL for specified currency to switch
     *
     * @param string $code Currency code
     * @return string
     */
    public function getSwitchCurrencyUrl($code)
    {
        return Mage::helper('directory/url')->getSwitchCurrencyUrl(['currency' => $code]);
    }

    /**
     * Retrieve Current Currency code
     *
     * @return string
     */
    public function getCurrentCurrencyCode()
    {
        if (is_null($this->_getData('current_currency_code'))) {
            $this->setData('current_currency_code', Mage::app()->getStore()->getCurrentCurrencyCode());
        }

        return $this->_getData('current_currency_code');
    }
}
