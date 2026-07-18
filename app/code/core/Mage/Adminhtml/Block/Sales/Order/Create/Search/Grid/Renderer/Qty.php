<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Search_Grid_Renderer_Qty extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Input
{
    /**
     * Returns whether this qty field must be inactive
     *
     * @param \Maho\DataObject $row
     * @return  bool
     */
    protected function _isInactive($row)
    {
        return $row->getTypeId() == Mage_Catalog_Model_Product_Type_Grouped::TYPE_CODE;
    }

    /**
     * Render product qty field
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        // Prepare values
        $isInactive = $this->_isInactive($row);

        if ($isInactive) {
            $qty = '';
        } else {
            $qty = $row->getData($this->getColumn()->getIndex());
            $qty *= 1;
            if (!$qty) {
                $qty = '';
            }
        }

        // Compose html
        $html = '<input type="text" ';
        $html .= 'name="' . $this->getColumn()->getId() . '" ';
        $html .= 'value="' . $qty . '" ';
        if ($isInactive) {
            $html .= 'disabled="disabled" ';
        }
        $html .= 'class="input-text ' . $this->getColumn()->getInlineCss() . ($isInactive ? ' input-inactive' : '') . '">';
        return $html;
    }
}
