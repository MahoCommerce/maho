<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

class Mage_Bundle_Block_Adminhtml_Catalog_Product_Composite_Fieldset_Bundle extends Mage_Bundle_Block_Catalog_Product_View_Type_Bundle
{
    /**
     * Returns string with json config for bundle product
     *
     * @return string
     */
    #[\Override]
    public function getJsonConfig()
    {
        $options = [];
        $optionsArray = $this->getOptions();
        foreach ($optionsArray as $option) {
            $optionId = $option->getId();
            $options[$optionId] = ['id' => $optionId, 'selections' => []];
            foreach ($option->getSelections() as $selection) {
                $options[$optionId]['selections'][$selection->getSelectionId()] = [
                    'can_change_qty' => $selection->getSelectionCanChangeQty(),
                    'default_qty'    => $selection->getSelectionQty(),
                ];
            }
        }
        $config = ['options' => $options];
        return Mage::helper('core')->jsonEncode($config);
    }
}
