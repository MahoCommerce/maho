<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Grid input column renderer
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Input extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_values;

    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $html = '<input type="text" ';
        $html .= 'name="' . $this->getColumn()->getId() . '" ';
        $html .= 'value="' . $row->getData($this->getColumn()->getIndex()) . '"';
        $html .= 'class="input-text ' . $this->getColumn()->getInlineCss() . '"/>';
        return $html;
    }
}
