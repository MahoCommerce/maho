<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Downloadable_Model_System_Config_Source_Contentdisposition
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'attachment',
                'label' => Mage::helper('downloadable')->__('attachment'),
            ],
            [
                'value' => 'inline',
                'label' => Mage::helper('downloadable')->__('inline'),
            ],
        ];
    }
}
