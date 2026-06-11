<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Tag extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('tag_grid');
        $this->setDefaultSort('name');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tag/tag')
            ->getResourceCollection()
            ->addProductFilter($this->getProductId())
            ->addPopularity();

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _afterLoadCollection()
    {
        return parent::_afterLoadCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => Mage::helper('catalog')->__('Tag Name'),
            'index'     => 'name',
        ]);

        $this->addColumn('popularity', [
            'header'        => Mage::helper('catalog')->__('# of Use'),
            'width'         => '50px',
            'index'         => 'popularity',
            'type'          => 'number',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('catalog')->__('Status'),
            'width'     => '90px',
            'index'     => 'status',
            'type'      => 'options',
            'options'   => [
                Mage_Tag_Model_Tag::STATUS_DISABLED => Mage::helper('catalog')->__('Disabled'),
                Mage_Tag_Model_Tag::STATUS_PENDING  => Mage::helper('catalog')->__('Pending'),
                Mage_Tag_Model_Tag::STATUS_APPROVED => Mage::helper('catalog')->__('Approved'),
            ],
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/tag/edit', [
            'tag_id'        => $row->getId(),
            'product_id'    => $this->getProductId(),
        ]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/catalog_product/tagGrid', [
            '_current'      => true,
            'id'            => $this->getProductId(),
            'product_id'    => $this->getProductId(),
        ]);
    }
}
