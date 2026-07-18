<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_System_Config_Source_PriceType
{
    protected $_options;

    public function __construct()
    {
        $this->_options = [
            [
                'value' => 0,
                'label' => Mage::helper('tax')->__('Excluding Tax'),
            ],
            [
                'value' => 1,
                'label' => Mage::helper('tax')->__('Including Tax'),
            ],
        ];
    }

    public function toOptionArray(): array
    {
        return $this->_options;
    }
}
