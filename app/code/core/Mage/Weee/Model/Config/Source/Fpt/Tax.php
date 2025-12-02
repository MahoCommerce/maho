<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Weee_Model_Config_Source_Fpt_Tax
{
    public function toOptionArray(): array
    {
        $weeeHelper = Mage::helper('weee');
        return [
            ['value' => 0, 'label' => $weeeHelper->__('Not Taxed')],
            ['value' => 1, 'label' => $weeeHelper->__('Taxed')],
            ['value' => 2, 'label' => $weeeHelper->__('Loaded and Displayed with Tax')],
        ];
    }
}
