<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Review_Detail_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Mage_Adminhtml_Block_Report_Review_Detail_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('reviews_grid');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('reports/review_collection')
            ->addProductFilter((int) $this->getRequest()->getParam('id'));
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('nickname', [
            'header'    => Mage::helper('reports')->__('Customer'),
            'width'     => '100px',
            'index'     => 'nickname',
        ]);

        $this->addColumn('title', [
            'header'    => Mage::helper('reports')->__('Title'),
            'width'     => '150px',
            'index'     => 'title',
        ]);

        $this->addColumn('detail', [
            'header'    => Mage::helper('reports')->__('Detail'),
            'index'     => 'detail',
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('reports')->__('Created At'),
            'index'     => 'created_at',
            'width'     => '200px',
            'type'      => 'datetime',
        ]);

        $this->setFilterVisibility(false);

        $this->addExportType('*/*/exportProductDetailCsv', Mage::helper('reports')->__('CSV'));
        $this->addExportType('*/*/exportProductDetailExcel', Mage::helper('reports')->__('Excel XML'));

        return parent::_prepareColumns();
    }
}
