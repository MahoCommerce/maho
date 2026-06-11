<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Api_Grid_Role extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('roleGrid');
        $this->setSaveParametersInSession(true);
        $this->setDefaultSort('role_id');
        $this->setDefaultDir('asc');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection =  Mage::getModel('api/roles')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('role_id', [
            'header'    => Mage::helper('adminhtml')->__('ID'),
            'index'     => 'role_id',
            'align'     => 'right',
            'width'    => '50px',
        ]);

        $this->addColumn('role_name', [
            'header'    => Mage::helper('adminhtml')->__('Role Name'),
            'index'     => 'role_name',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/roleGrid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/editrole', ['rid' => $row->getRoleId()]);
    }
}
