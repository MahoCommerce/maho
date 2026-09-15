<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Concat extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        return $this->escapeHtml($this->_concat($row));
    }

    /**
     * Export is not html, so it keeps the raw value.
     */
    #[\Override]
    public function renderExport(\Maho\DataObject $row)
    {
        return $this->_concat($row);
    }

    protected function _concat(\Maho\DataObject $row): string
    {
        $dataArr = [];
        foreach ((array) $this->getColumn()->getIndex() as $index) {
            if ($data = $row->getData($index)) {
                $dataArr[] = $data;
            }
        }
        return implode($this->getColumn()->getSeparator(), $dataArr);
    }
}
