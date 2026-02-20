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

class Mage_Adminhtml_Model_System_Config_Source_Tax_Apply_On
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 0, 'label' => Mage::helper('tax')->__('Custom price if available')],
            ['value' => 1, 'label' => Mage::helper('tax')->__('Original price only')],
        ];
    }
}
