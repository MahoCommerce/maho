<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_Config_Price_Include extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();
        Mage::app()->cleanCache('checkout_quote');
        return $this;
    }
}
