<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Container extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * @var string|null
     */
    protected $_addButtonLabel;

    /**
     * @var string|null
     */
    protected $_backButtonLabel;

    /**
     * @var string
     */
    protected $_blockGroup = 'adminhtml';

    /**
     * @var string
     */
    protected $_block;

    /**
     * Mage_Adminhtml_Block_Widget_Grid_Container constructor.
     */
    public function __construct()
    {
        if (is_null($this->_addButtonLabel)) {
            $this->_addButtonLabel = $this->__('Add New');
        }
        if (is_null($this->_backButtonLabel)) {
            $this->_backButtonLabel = $this->__('Back');
        }

        parent::__construct();

        $this->setTemplate('widget/grid/container.phtml');

        $this->_addButton('add', [
            'label'     => $this->getAddButtonLabel(),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getCreateUrl()),
            'class'     => 'add',
        ]);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'grid',
            $this->getLayout()->createBlock(
                $this->_blockGroup . '/' . $this->_controller . '_grid',
                $this->_controller . '.grid',
            )->setSaveParametersInSession(true),
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getCreateUrl()
    {
        return $this->getUrl('*/*/new');
    }

    /**
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid');
    }

    /**
     * @return string
     */
    protected function getAddButtonLabel()
    {
        return $this->_addButtonLabel;
    }

    /**
     * @return string
     */
    protected function getBackButtonLabel()
    {
        return $this->_backButtonLabel;
    }

    protected function _addBackButton()
    {
        $this->_addButton('back', [
            'label'     => $this->getBackButtonLabel(),
            'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getBackUrl()),
            'class'     => 'back',
        ]);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderCssClass()
    {
        return 'icon-head ' . parent::getHeaderCssClass();
    }

    /**
     * @return string
     */
    public function getHeaderWidth()
    {
        return 'width:50%;';
    }
}
