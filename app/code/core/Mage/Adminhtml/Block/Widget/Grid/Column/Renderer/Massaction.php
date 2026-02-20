<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Massaction extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Checkbox
{
    protected $_defaultWidth = 20;

    /**
     * Render header of the row
     *
     * @return string
     */
    #[\Override]
    public function renderHeader()
    {
        return '&nbsp;';
    }

    /**
     * Render HTML properties
     *
     * @return string
     */
    #[\Override]
    public function renderProperty()
    {
        $out = parent::renderProperty();
        $out = preg_replace('/class=".*?"/i', '', $out);
        $out .= ' class="a-center"';
        return $out;
    }

    /**
     * Returns HTML of the object
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($this->getColumn()->getGrid()->getMassactionIdFieldOnlyIndexValue()) {
            $this->setNoObjectId(true);
        }
        return parent::render($row);
    }

    /**
     * Returns HTML of the checkbox
     *
     * @param string $value
     * @param bool   $checked
     * @return string
     */
    #[\Override]
    protected function _getCheckboxHtml($value, $checked)
    {
        $html = '<input type="checkbox" name="' . $this->getColumn()->getName() . '" ';
        $html .= 'value="' . $this->escapeHtml($value) . '" class="massaction-checkbox"' . $checked . '/>';
        return $html;
    }
}
