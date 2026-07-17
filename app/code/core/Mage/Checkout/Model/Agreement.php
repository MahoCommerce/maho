<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @method Mage_Checkout_Model_Resource_Agreement _getResource()
 * @method Mage_Checkout_Model_Resource_Agreement getResource()
 * @method Mage_Checkout_Model_Resource_Agreement_Collection getCollection()
 *
 * @method string getName()
 * @method $this setName(string $value)
 * @method string getContent()
 * @method $this setContent(string $value)
 * @method string getContentHeight()
 * @method $this setContentHeight(string $value)
 * @method string getCheckboxText()
 * @method $this setCheckboxText(string $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method int getIsHtml()
 * @method $this setIsHtml(int $value)
 * @method int getStoreId()
 */

class Mage_Checkout_Model_Agreement extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('checkout/agreement');
    }
}
