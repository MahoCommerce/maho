<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Block_Account_Changeforgotten extends Mage_Core_Block_Template
{
    /**
     * Retrieve minimum length of customer password
     *
     * @return int
     */
    public function getMinPasswordLength()
    {
        return Mage::getModel('customer/customer')->getMinPasswordLength();
    }
}
