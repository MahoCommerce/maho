<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tax_Class_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('taxClassGrid');
        $this->setDefaultSort('class_name');
        $this->setDefaultDir('ASC');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tax/class')
            ->getCollection()
            ->setClassTypeFilter($this->getClassType());
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn(
            'class_name',
            [
                'header'    => Mage::helper('tax')->__('Class Name'),
                'align'     => 'left',
                'index'     => 'class_name',
            ],
        );

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
