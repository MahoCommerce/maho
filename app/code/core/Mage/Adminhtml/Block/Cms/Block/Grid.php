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

class Mage_Adminhtml_Block_Cms_Block_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('cmsBlockGrid');
        $this->setDefaultSort('title');
        $this->setDefaultDir('ASC');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('cms/block')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('title', [
            'header'    => Mage::helper('cms')->__('Title'),
            'align'     => 'left',
            'index'     => 'title',
        ]);

        $this->addColumn('identifier', [
            'header'    => Mage::helper('cms')->__('Identifier'),
            'align'     => 'left',
            'index'     => 'identifier',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header'        => Mage::helper('cms')->__('Store View'),
                'index'         => 'store_id',
                'type'          => 'store',
                'store_all'     => true,
                'store_view'    => true,
                'sortable'      => false,
                'filter_condition_callback'
                                => [$this, '_filterStoreCondition'],
            ]);
        }

        $this->addColumn('is_active', [
            'header'    => Mage::helper('cms')->__('Status'),
            'index'     => 'is_active',
            'type'      => 'options',
            'options'   => [
                0 => Mage::helper('cms')->__('Disabled'),
                1 => Mage::helper('cms')->__('Enabled'),
            ],
        ]);

        $this->addColumn('creation_time', [
            'header'    => Mage::helper('cms')->__('Date Created'),
            'index'     => 'creation_time',
            'type'      => 'datetime',
        ]);

        $this->addColumn('update_time', [
            'header'    => Mage::helper('cms')->__('Last Modified'),
            'index'     => 'update_time',
            'type'      => 'datetime',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _afterLoadCollection()
    {
        $this->getCollection()->walk('afterLoad');
        return parent::_afterLoadCollection();
    }

    /**
     * @param Mage_Cms_Model_Resource_Block_Collection $collection
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     */
    protected function _filterStoreCondition($collection, $column)
    {
        if ($value = $column->getFilter()->getValue()) {
            $collection->addStoreFilter($value);
        }
    }

    /**
     * Row click url
     *
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['block_id' => $row->getId()]);
    }
}
