<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_CurrencyController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/directory/currency/switch', name: 'directory.currency.switch')]
    public function switchAction(): void
    {
        if ($curency = (string) $this->getRequest()->getParam('currency')) {
            Mage::app()->getStore()->setCurrentCurrencyCode($curency);
        }
        $this->_redirectReferer(Mage::getBaseUrl());
    }
}
