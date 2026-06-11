<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

class Mage_Tax_Model_System_Config_Source_Tax_Country extends Mage_Adminhtml_Model_System_Config_Source_Country
{
    protected $_options;

    /**
     * @param bool $noEmpty
     * @return array
     */
    #[\Override]
    public function toOptionArray($noEmpty = false)
    {
        $options = parent::toOptionArray($noEmpty);

        if (!$noEmpty) {
            if ($options) {
                $options[0]['label'] = Mage::helper('tax')->__('None');
            } else {
                $options = [['value' => '', 'label' => Mage::helper('tax')->__('None')]];
            }
        }

        return $options;
    }
}
