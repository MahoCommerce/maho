<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Theme extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $options = $this->getOptions();
        $value   = $this->_getValue($row);
        if ($value == '') {
            $value = 'all';
        }

        return $this->escapeHtml($this->_getValueLabel($options, $value));
    }

    /**
     * Retrieve options set in column.
     * Or load if options was not set.
     *
     * @return array
     */
    public function getOptions()
    {
        if ($this->getColumn()->getFilter()) {
            $options = $this->getColumn()->getFilter()->getOptions();
        } else {
            $options = $this->getColumn()->getOptions();
        }
        if (empty($options) || !is_array($options)) {
            $options = Mage::getModel('core/design_source_design')
                ->setIsFullLabel(true)->getAllOptions(false);
        }

        return $options;
    }

    /**
     * Retrieve value label from options array
     *
     * @param array $options
     * @param string $value
     * @return mixed
     */
    protected function _getValueLabel($options, $value)
    {
        if (empty($options) || !is_array($options)) {
            return false;
        }

        foreach ($options as $option) {
            if (is_array($option['value'])) {
                $label = $this->_getValueLabel($option['value'], $value);
                if ($label) {
                    return $label;
                }
            } else {
                if ($option['value'] == $value) {
                    return $option['label'];
                }
            }
        }

        return false;
    }
}
