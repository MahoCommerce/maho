<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Dashboard_Tab_Customers_Newest extends Mage_Adminhtml_Block_Dashboard_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customersNewestGrid');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('reports/customer_collection')
            ->addCustomerName();

        $storeFilter = 0;
        if ($this->getParam('store')) {
            $collection->addAttributeToFilter('store_id', $this->getParam('store'));
            $storeFilter = 1;
        } elseif ($this->getParam('website')) {
            $storeIds = Mage::app()->getWebsite($this->getParam('website'))->getStoreIds();
            $collection->addAttributeToFilter('store_id', ['in' => $storeIds]);
        } elseif ($this->getParam('group')) {
            $storeIds = Mage::app()->getGroup($this->getParam('group'))->getStoreIds();
            $collection->addAttributeToFilter('store_id', ['in' => $storeIds]);
        }

        $collection->addOrdersStatistics($storeFilter)
            ->orderByCustomerRegistration();

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => $this->__('Customer Name'),
            'sortable'  => false,
            'index'     => 'name',
        ]);

        $this->addColumn('orders_count', [
            'header'    => $this->__('Number of Orders'),
            'sortable'  => false,
            'index'     => 'orders_count',
            'type'      => 'number',
        ]);

        $baseCurrencyCode = (string) Mage::app()->getStore((int) $this->getParam('store'))->getBaseCurrencyCode();

        $this->addColumn('orders_avg_amount', [
            'header'    => $this->__('Average Order Amount'),
            'sortable'  => false,
            'type'      => 'currency',
            'currency_code'  => $baseCurrencyCode,
            'index'     => 'orders_avg_amount',
            'renderer'  => 'adminhtml/report_grid_column_renderer_currency',
        ]);

        $this->addColumn('orders_sum_amount', [
            'header'    => $this->__('Total Order Amount'),
            'sortable'  => false,
            'type'      => 'currency',
            'currency_code'  => $baseCurrencyCode,
            'index'     => 'orders_sum_amount',
            'renderer'  => 'adminhtml/report_grid_column_renderer_currency',
        ]);

        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/customer/edit', ['id' => $row->getId()]);
    }
}
