<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Review_Product_Grid extends Mage_Adminhtml_Block_Catalog_Product_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('reviewProductGrid');
        $this->setRowClickCallback('review.gridRowClick.bind(review)');
        $this->setUseAjax(true);
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', [
            'header'    => Mage::helper('review')->__('ID'),
            'index'     => 'entity_id',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('review')->__('Name'),
            'index'     => 'name',
        ]);

        if ((int) $this->getRequest()->getParam('store', 0)) {
            $this->addColumn('custom_name', [
                'header'    => Mage::helper('review')->__('Name in Store'),
                'index'     => 'custom_name',
            ]);
        }

        $this->addColumn('sku', [
            'header'    => Mage::helper('review')->__('SKU'),
            'width'     => '80px',
            'index'     => 'sku',
        ]);

        $this->addColumn('price', [
            'type'      => 'currency',
        ]);

        $this->addColumn('qty', [
            'header'    => Mage::helper('review')->__('Qty'),
            'type'      => 'number',
            'index'     => 'qty',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('review')->__('Status'),
            'width'     => '90px',
            'index'     => 'status',
            'type'      => 'options',
            'source'    => 'catalog/product_status',
            'options'   => Mage::getSingleton('catalog/product_status')->getOptionArray(),
        ]);

        /**
         * Check is single store mode
         */
        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'websites',
                [
                    'header' => Mage::helper('review')->__('Websites'),
                    'width' => '100px',
                    'sortable'  => false,
                    'index'     => 'websites',
                    'type'      => 'options',
                    'options'   => Mage::getModel('core/website')->getCollection()->toOptionHash(),
                ],
            );
        }

        return $this;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/productGrid', ['_current' => true]);
    }

    /**
     * @param \Maho\DataObject $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/jsonProductInfo', ['id' => $row->getId()]);
    }

    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        return $this;
    }
}
