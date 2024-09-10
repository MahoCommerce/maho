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
 * Adminhtml grid item renderer
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Grid_Block extends Varien_Filter_Object implements Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Interface
{
    #[\Override]
    public function render(Varien_Object $row)
    {
        $block->setPageObject($row);
        echo $block->toHtml();
    }
}
