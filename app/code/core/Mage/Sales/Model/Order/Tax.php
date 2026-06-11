<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * @method Mage_Sales_Model_Resource_Order_Tax _getResource()
 * @method Mage_Sales_Model_Resource_Order_Tax getResource()
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method string getTitle()
 * @method $this setTitle(string $value)
 * @method float getPercent()
 * @method $this setPercent(float $value)
 * @method float getAmount()
 * @method $this setAmount(float $value)
 * @method int getPriority()
 * @method $this setPriority(int $value)
 * @method int getPosition()
 * @method $this setPosition(int $value)
 * @method float getBaseAmount()
 * @method $this setBaseAmount(float $value)
 * @method int getProcess()
 * @method $this setProcess(int $value)
 * @method float getBaseRealAmount()
 * @method $this setBaseRealAmount(float $value)
 * @method int getHidden()
 * @method $this setHidden(int $value)
 */

class Mage_Sales_Model_Order_Tax extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_tax');
    }
}
