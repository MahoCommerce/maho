<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Options extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{
    /**
     * Render a grid cell as options
     *
     * @return string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $options = $this->getColumn()->getOptions();
        $showMissingOptionValues = (bool) $this->getColumn()->getShowMissingOptionValues();
        if (!empty($options) && is_array($options)) {
            $value = $row->getData($this->getColumn()->getIndex());
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
            } elseif (isset($options[$value])) {
                return $this->escapeHtml($options[$value]);
            } elseif (in_array($value, $options)) {
                return $this->escapeHtml($value);
            }
        }
        return '';
    }
}
