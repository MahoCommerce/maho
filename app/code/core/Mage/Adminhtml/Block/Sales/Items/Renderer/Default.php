<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Sales_Items_Renderer_Default extends Mage_Adminhtml_Block_Sales_Items_Abstract
{
    public function getItem(): Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Creditmemo_Item|Mage_Sales_Model_Order_Shipment_Item|null
    {
        return $this->_getData('item');
    }
}
