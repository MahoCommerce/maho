<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Role_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('apiplatformRoleGrid');
        $this->setDefaultSort('role_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $resource = Mage::getSingleton('core/resource');
        $roleTable = $resource->getTableName('api/role');

        $collection = new Maho\Data\Collection\Db(
            $resource->getConnection('core_read'),
        );
        $collection->getSelect()
            ->from(['main_table' => $roleTable])
            ->where('role_type = ?', 'G');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $this->addColumn('role_id', [
            'header' => $this->__('ID'),
            'width'  => '50px',
            'index'  => 'role_id',
        ]);

        $this->addColumn('role_name', [
            'header' => $this->__('Role Name'),
            'index'  => 'role_name',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['role_id' => $row->getData('role_id')]);
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
