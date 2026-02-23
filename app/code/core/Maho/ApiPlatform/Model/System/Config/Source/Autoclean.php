<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API cache auto-clean mode source model
 */
class Maho_ApiPlatform_Model_System_Config_Source_Autoclean
{
    public const DISABLED = 'disabled';
    public const PRODUCT_ONLY = 'product';
    public const INVENTORY_ONLY = 'inventory';
    public const ALL = 'all';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ALL, 'label' => Mage::helper('maho_apiplatform')->__('All changes (product, inventory, category, price, reviews)')],
            ['value' => self::PRODUCT_ONLY, 'label' => Mage::helper('maho_apiplatform')->__('Product & category changes only')],
            ['value' => self::INVENTORY_ONLY, 'label' => Mage::helper('maho_apiplatform')->__('Inventory changes only')],
            ['value' => self::DISABLED, 'label' => Mage::helper('maho_apiplatform')->__('Disabled (manual only)')],
        ];
    }
}
