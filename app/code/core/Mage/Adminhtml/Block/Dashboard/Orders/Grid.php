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

class Mage_Adminhtml_Block_Dashboard_Orders_Grid extends Mage_Adminhtml_Block_Dashboard_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('lastOrdersGrid');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        if (!$this->isModuleEnabled('Mage_Reports')) {
            return $this;
        }
        $collection = Mage::getResourceModel('reports/order_collection')
            ->addItemCountExpr()
            ->joinCustomerName('customer')
            ->orderByCreatedAt();

        if ($this->getParam('store') || $this->getParam('website') || $this->getParam('group')) {
            if ($this->getParam('store')) {
                $collection->addAttributeToFilter('store_id', $this->getParam('store'));
            } elseif ($this->getParam('website')) {
                $storeIds = Mage::app()->getWebsite($this->getParam('website'))->getStoreIds();
                $collection->addAttributeToFilter('store_id', ['in' => $storeIds]);
            } elseif ($this->getParam('group')) {
                $storeIds = Mage::app()->getGroup($this->getParam('group'))->getStoreIds();
                $collection->addAttributeToFilter('store_id', ['in' => $storeIds]);
            }

            $collection->addRevenueToSelect();
        } else {
            $collection->addRevenueToSelect(true);
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepares page sizes for dashboard grid with las 5 orders
     */
    #[\Override]
    protected function _preparePage()
    {
        $this->getCollection()->setPageSize($this->getParam($this->getVarNameLimit(), $this->_defaultLimit));
        // Remove count of total orders $this->getCollection()->setCurPage($this->getParam($this->getVarNamePage(), $this->_defaultPage));
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('customer', [
            'header'    => $this->__('Customer'),
            'sortable'  => false,
            'index'     => 'customer',
            'default'   => $this->__('Guest'),
        ]);

        $this->addColumn('items', [
            'header'    => $this->__('Items'),
            'type'      => 'number',
            'sortable'  => false,
            'index'     => 'items_count',
        ]);

        $baseCurrencyCode = Mage::app()->getStore((int) $this->getParam('store'))->getBaseCurrencyCode();

        $this->addColumn('total', [
            'header'    => $this->__('Grand Total'),
            'sortable'  => false,
            'type'      => 'currency',
            'currency_code'  => $baseCurrencyCode,
            'index'     => 'revenue',
        ]);

        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);

        return parent::_prepareColumns();
    }

    /**
     * @param Varien_Object $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $row->getId()]);
    }
}
