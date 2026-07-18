<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Options extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{
    /**
     * Render a grid cell as options
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $options = $this->getColumn()->getOptions();
        $showMissingOptionValues = (bool) $this->getColumn()->getShowMissingOptionValues();
        if (!empty($options) && is_array($options)) {
            $value = $row->getData($this->getColumn()->getIndex());
            if ($value === null) {
                return '';
            }
            if (is_array($value)) {
                $res = [];
                foreach ($value as $item) {
                    if (isset($options[$item])) {
                        $res[] = $this->escapeHtml($options[$item]);
                    } elseif ($showMissingOptionValues) {
                        $res[] = $this->escapeHtml($item);
                    }
                }
                return implode(', ', $res);
            }
            if (isset($options[$value])) {
                return $this->escapeHtml($options[$value]);
            }
            if (in_array($value, $options)) {
                return $this->escapeHtml($value);
            }
        }
        return '';
    }
}
