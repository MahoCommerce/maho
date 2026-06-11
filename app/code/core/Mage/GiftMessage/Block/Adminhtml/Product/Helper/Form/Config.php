<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

declare(strict_types=1);

class Mage_GiftMessage_Block_Adminhtml_Product_Helper_Form_Config extends Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Config
{
    /**
     * Get config value data
     *
     * @return bool
     */
    #[\Override]
    protected function _getValueFromConfig()
    {
        return Mage::getStoreConfigFlag(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS);
    }
}
