<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Currency_Service
{
    protected $_options;

    public function toOptionArray($isMultiselect)
    {
        if (!$this->_options) {
            $services = Mage::getConfig()->getNode('global/currency/import/services')->asArray();
            $currencyConfig = Mage::getStoreConfig('currency');
            $this->_options = [];
            foreach ($services as $code => $options) {
                if (isset($currencyConfig[$code]['active']) && $currencyConfig[$code]['active'] === '0') {
                    continue;
                }
                $this->_options[] = [
                    'label' => $options['name'],
                    'value' => $code,
                ];
            }
        }

        return $this->_options;
    }
}
