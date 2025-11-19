<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogInventory_Model_Source_Backorders
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Mage_CatalogInventory_Model_Stock::BACKORDERS_NO, 'label' => Mage::helper('cataloginventory')->__('No Backorders')],
            ['value' => Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY, 'label' => Mage::helper('cataloginventory')->__('Allow Qty Below 0')],
            ['value' => Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY , 'label' => Mage::helper('cataloginventory')->__('Allow Qty Below 0 and Notify Customer')],
        ];
    }
}
