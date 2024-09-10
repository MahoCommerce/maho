<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Text grid column filter
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Text extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    #[\Override]
    public function getHtml()
    {
        return '<div class="field-100"><input type="text" name="' . $this->_getHtmlName() . '" id="' . $this->_getHtmlId() . '" value="' . $this->getEscapedValue() . '" class="input-text no-changes"/></div>';
    }
}
