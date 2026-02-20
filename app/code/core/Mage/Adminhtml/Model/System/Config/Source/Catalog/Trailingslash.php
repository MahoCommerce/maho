<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash
{
    public const REMOVE_TRAILING_SLASH = 'remove';
    public const ADD_TRAILING_SLASH = 'add';
    public const DO_NOTHING = 'nothing';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::REMOVE_TRAILING_SLASH, 'label' => Mage::helper('adminhtml')->__('Redirect to URL without trailing slash')],
            ['value' => self::ADD_TRAILING_SLASH, 'label' => Mage::helper('adminhtml')->__('Redirect to URL with trailing slash')],
            ['value' => self::DO_NOTHING, 'label' => Mage::helper('adminhtml')->__('Do nothing')],
        ];
    }
}
