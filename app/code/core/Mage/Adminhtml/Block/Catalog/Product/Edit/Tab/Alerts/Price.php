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

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Alerts_Price extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();

        $this->setId('alertPrice');
        $this->setDefaultSort('add_date');
        $this->setDefaultDir('desc');
        $this->setUseAjax(true);
        $this->setFilterVisibility(false);
        $this->setEmptyText(Mage::helper('catalog')->__('There are no customers for this alert'));
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $productId = $this->getRequest()->getParam('id');
        $websiteId = 0;
        if ($store = $this->getRequest()->getParam('store')) {
            $websiteId = Mage::app()->getStore($store)->getWebsiteId();
        }
        if ($this->isModuleEnabled('Mage_ProductAlert', 'catalog')) {
            $collection = Mage::getModel('productalert/price')
                ->getCustomerCollection()
                ->join($productId, $websiteId);
            $this->setCollection($collection);
        }
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('firstname', [
            'header'    => Mage::helper('catalog')->__('First Name'),
            'index'     => 'firstname',
        ]);

        $this->addColumn('middlename', [
            'header'    => Mage::helper('catalog')->__('Middle Name'),
            'index'     => 'middlename',
        ]);

        $this->addColumn('lastname', [
            'header'    => Mage::helper('catalog')->__('Last Name'),
            'index'     => 'lastname',
        ]);

        $this->addColumn('email', [
            'header'    => Mage::helper('catalog')->__('Email'),
            'index'     => 'email',
        ]);

        $this->addColumn('price', [
            'type'      => 'currency',
            'currency_code'
                        => Mage_Directory_Helper_Data::getConfigCurrencyBase(),
        ]);

        $this->addColumn('add_date', [
            'header'    => Mage::helper('catalog')->__('Date Subscribed'),
            'index'     => 'add_date',
            'type'      => 'date',
        ]);

        $this->addColumn('last_send_date', [
            'header'    => Mage::helper('catalog')->__('Last Notification'),
            'index'     => 'last_send_date',
            'type'      => 'date',
        ]);

        $this->addColumn('send_count', [
            'header'    => Mage::helper('catalog')->__('Send Count'),
            'index'     => 'send_count',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl()
    {
        $productId = $this->getRequest()->getParam('id');
        $storeId   = $this->getRequest()->getParam('store', 0);
        if ($storeId) {
            $storeId = Mage::app()->getStore($storeId)->getId();
        }
        return $this->getUrl('*/catalog_product/alertsPriceGrid', [
            'id'    => $productId,
            'store' => $storeId,
        ]);
    }
}
