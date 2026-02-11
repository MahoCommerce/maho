<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_GiftMessage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
