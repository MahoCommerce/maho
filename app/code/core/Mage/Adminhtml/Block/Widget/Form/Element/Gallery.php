<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Form_Element_Gallery extends Mage_Adminhtml_Block_Template implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    protected $_element = null;

    public function __construct()
    {
        $this->setTemplate('widget/form/element/gallery.phtml');
    }

    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $this->setElement($element);
        return $this->toHtml();
    }

    public function setElement(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $this->_element = $element;
        return $this;
    }

    public function getElement()
    {
        return $this->_element;
    }

    public function getValues()
    {
        return $this->getElement()->getValue();
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('adminhtml')->__('Delete'),
                    'onclick'   => 'deleteImage(#image#)',
                    'class' => 'delete',
                ]),
        );

        $this->setChild(
            'add_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('adminhtml')->__('Add New Image'),
                    'onclick'   => 'addNewImage()',
                    'class' => 'add',
                ]),
        );
        return parent::_prepareLayout();
    }

    public function getAddButtonHtml()
    {
        return $this->getChildHtml('add_button');
    }

    public function getDeleteButtonHtml($image)
    {
        return str_replace('#image#', $image, $this->getChildHtml('delete_button'));
    }
}
