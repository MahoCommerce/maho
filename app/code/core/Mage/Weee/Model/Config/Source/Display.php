<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

class Mage_Weee_Model_Config_Source_Display
{
    public function toOptionArray(): array
    {
        /**
         * VAT is not applicable to FPT separately (we can't have FPT incl/excl VAT)
         */
        return [
            [
                'value' => 0,
                'label' => Mage::helper('weee')->__('Including FPT only'),
            ],
            [
                'value' => 1,
                'label' => Mage::helper('weee')->__('Including FPT and FPT description'),
            ],
            //array('value'=>4, 'label'=>Mage::helper('weee')->__('Including FPT and FPT description [incl. FPT VAT]')),
            [
                'value' => 2,
                'label' => Mage::helper('weee')->__('Excluding FPT, FPT description, final price'),
            ],
            [
                'value' => 3,
                'label' => Mage::helper('weee')->__('Excluding FPT'),
            ],
        ];
    }
}
