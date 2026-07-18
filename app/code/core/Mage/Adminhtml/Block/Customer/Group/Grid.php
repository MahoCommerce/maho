<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Group_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customerGroupGrid');
        $this->setDefaultSort('type');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('customer/group_collection')
            ->addTaxClass();

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Configuration of grid
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('time', [
            'header' => Mage::helper('customer')->__('ID'),
            'width' => '50px',
            'align' => 'right',
            'index' => 'customer_group_id',
        ]);

        $this->addColumn('type', [
            'header' => Mage::helper('customer')->__('Group Name'),
            'index' => 'customer_group_code',
        ]);

        $this->addColumn('class_name', [
            'header' => Mage::helper('customer')->__('Tax Class'),
            'index' => 'class_name',
            'width' => '200px',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
