<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Set_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('setGrid');
        $this->setDefaultSort('set_name');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter(Mage::registry('entityType'));

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return $this
     * @throws Exception
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('attribute_set_id', [
            'header'    => Mage::helper('eav')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'type'      => 'number',
            'index'     => 'attribute_set_id',
        ]);

        $this->addColumn('set_name', [
            'header'    => Mage::helper('eav')->__('Set Name'),
            'align'     => 'left',
            'index'     => 'attribute_set_name',
        ]);

        return $this;
    }

    /**
     * @param \Maho\DataObject $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getAttributeSetId()]);
    }
}
