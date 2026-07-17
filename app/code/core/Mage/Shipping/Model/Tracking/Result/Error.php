<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

class Mage_Shipping_Model_Tracking_Result_Error extends Mage_Shipping_Model_Tracking_Result_Abstract
{
    /**
     * @return array
     */
    public function getAllData()
    {
        return $this->_data;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return  Mage::helper('shipping')->__('Tracking information is currently unavailable.');
    }
}
