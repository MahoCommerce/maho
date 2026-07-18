<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Wrapline extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Default max length of a line at one row
     *
     * @var int
     */
    protected $_defaultMaxLineLength = 60;

    /**
     * Renders grid column
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $line = parent::_getValue($row);
        $wrappedLine = '';
        $lineLength = $this->getColumn()->getData('lineLength') ?: $this->_defaultMaxLineLength;
        for ($i = 0, $n = floor(Mage::helper('core/string')->strlen($line) / $lineLength); $i <= $n; $i++) {
            $wrappedLine .= Mage::helper('core/string')->substr($line, ($lineLength * $i), $lineLength) . '<br>';
        }
        return $wrappedLine;
    }
}
