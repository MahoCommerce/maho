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

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Alerts extends Mage_Adminhtml_Block_Template
{
    /**
     * Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Alerts constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product/tab/alert.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        /** @var Mage_Adminhtml_Block_Widget_Accordion $accordion */
        $accordion = $this->getLayout()->createBlock('adminhtml/widget_accordion');
        $accordion->setId('productAlerts');

        $alertPriceAllow = Mage::getStoreConfig('catalog/productalert/allow_price');
        $alertStockAllow = Mage::getStoreConfig('catalog/productalert/allow_stock');

        if ($alertPriceAllow) {
            $accordion->addItem('price', [
                'title'     => Mage::helper('adminhtml')->__('Price alert subscription was saved.'),
                'content'   => $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_alerts_price')->toHtml() . '<br />',
                'open'      => true,
            ]);
        }
        if ($alertStockAllow) {
            $accordion->addItem('stock', [
                'title'     => Mage::helper('adminhtml')->__('Stock notification was saved.'),
                'content'   => $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_alerts_stock'),
                'open'      => true,
            ]);
        }

        $this->setChild('accordion', $accordion);

        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getAccordionHtml()
    {
        return $this->getChildHtml('accordion');
    }
}
