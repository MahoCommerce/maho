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

class Mage_Adminhtml_Block_Widget_Form_Element extends Mage_Adminhtml_Block_Template
{
    protected $_element;
    protected $_form;
    protected $_formBlock;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('widget/form/element.phtml');
    }

    public function setElement($element)
    {
        $this->_element = $element;
        return $this;
    }

    public function setForm($form)
    {
        $this->_form = $form;
        return $this;
    }

    public function setFormBlock($formBlock)
    {
        $this->_formBlock = $formBlock;
        return $this;
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->assign('form', $this->_form);
        $this->assign('element', $this->_element);
        $this->assign('formBlock', $this->_formBlock);

        return parent::_beforeToHtml();
    }
}
