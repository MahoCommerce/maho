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

class Mage_Adminhtml_Block_Report_Product_Lowstock_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('gridLowstock');
        $this->setUseAjax(false);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        if ($this->getRequest()->getParam('website')) {
            $storeIds = Mage::app()->getWebsite($this->getRequest()->getParam('website'))->getStoreIds();
            $storeId = array_pop($storeIds);
        } elseif ($this->getRequest()->getParam('group')) {
            $storeIds = Mage::app()->getGroup($this->getRequest()->getParam('group'))->getStoreIds();
            $storeId = array_pop($storeIds);
        } elseif ($this->getRequest()->getParam('store')) {
            $storeId = (int) $this->getRequest()->getParam('store');
        } else {
            $storeId = '';
        }

        /** @var Mage_Reports_Model_Resource_Product_Lowstock_Collection $collection */
        $collection = Mage::getResourceModel('reports/product_lowstock_collection')
            ->addAttributeToSelect('*')
            ->setStoreId($storeId)
            ->filterByIsQtyProductTypes()
            ->joinInventoryItem('qty')
            ->useManageStockFilter($storeId)
            ->useNotifyStockQtyFilter($storeId)
            ->setOrder('qty', \Maho\Data\Collection::SORT_ORDER_ASC);

        if ($storeId) {
            $collection->addStoreFilter($storeId);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => Mage::helper('reports')->__('Product Name'),
            'sortable'  => false,
            'index'     => 'name',
        ]);

        $this->addColumn('sku', [
            'header'    => Mage::helper('reports')->__('Product SKU'),
            'sortable'  => false,
            'index'     => 'sku',
        ]);

        $this->addColumn('qty', [
            'header'    => Mage::helper('reports')->__('Stock Qty'),
            'width'     => '215px',
            'sortable'  => false,
            'filter'    => 'adminhtml/widget_grid_column_filter_range',
            'index'     => 'qty',
            'type'      => 'number',
        ]);

        $this->addExportType('*/*/exportLowstockCsv', Mage::helper('reports')->__('CSV'));
        $this->addExportType('*/*/exportLowstockExcel', Mage::helper('reports')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
