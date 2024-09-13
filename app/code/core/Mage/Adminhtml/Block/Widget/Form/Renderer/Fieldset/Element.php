<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Fieldset element renderer
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element extends Mage_Adminhtml_Block_Template implements Varien_Data_Form_Element_Renderer_Interface
{
    protected $_element;

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('widget/form/renderer/fieldset/element.phtml');
    }

    public function getElement()
    {
        return $this->_element;
    }

    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $this->_element = $element;
        return $this->toHtml();
    }
}
