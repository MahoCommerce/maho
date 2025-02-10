<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('blogPostGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        /** @var Maho_Blog_Model_Resource_Post_Collection $collection */
        $collection = Mage::getModel('blog/post')->getCollection();
        $collection->addAttributeToSelect('title');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', [
            'header' => Mage::helper('blog')->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'entity_id',
        ]);

        $this->addColumn('title', [
            'header' => Mage::helper('blog')->__('Title'),
            'align' => 'left',
            'index' => 'title',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', [
                'header'        => Mage::helper('blog')->__('Store View'),
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
            'header'    => Mage::helper('blog')->__('Status'),
            'index'     => 'is_active',
            'type'      => 'options',
            'options'   => [
                0 => Mage::helper('blog')->__('Disabled'),
                1 => Mage::helper('blog')->__('Enabled'),
            ],
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('blog')->__('Created At'),
            'width' => '150px',
            'index' => 'created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn(
            'action',
            [
                'header' => Mage::helper('blog')->__('Action'),
                'width' => '100px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => [
                    [
                        'caption' => Mage::helper('blog')->__('Edit'),
                        'url' => ['base' => '*/*/edit'],
                        'field' => 'id',
                    ],
                ],
                'filter' => false,
                'sortable' => false,
                'is_system' => true,
            ],
        );

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('post');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('blog')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('blog')->__('Are you sure?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }

    protected function _filterStoreCondition(Maho_Blog_Model_Resource_Post_Collection $collection, Mage_Adminhtml_Block_Widget_Grid_Column $column)
    {
        if ($value = $column->getFilter()->getValue()) {
            $collection->addStoreFilter($value);
        }
    }
}
