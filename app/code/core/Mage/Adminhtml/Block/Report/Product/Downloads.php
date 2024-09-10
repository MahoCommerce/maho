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
 * Adminhtml product downloads report
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Report_Product_Downloads extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'report_product_downloads';
        $this->_headerText = Mage::helper('reports')->__('Downloads');
        parent::__construct();
        $this->_removeButton('add');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'store_switcher',
            $this->getLayout()->createBlock('adminhtml/store_switcher')
                ->setUseConfirm(false)
                ->setSwitchUrl($this->getUrl('*/*/*', ['store' => null]))
                ->setTemplate('report/store/switcher.phtml')
        );
        return parent::_prepareLayout();
    }

    public function getStoreSwitcherHtml()
    {
        return Mage::app()->isSingleStoreMode() ? '' : $this->getChildHtml('store_switcher');
    }

    #[\Override]
    public function getGridHtml()
    {
        return $this->getStoreSwitcherHtml() . parent::getGridHtml();
    }

    #[\Override]
    public function getHeaderCssClass()
    {
        return 'icon-head head-report';
    }
}
