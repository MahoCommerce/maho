<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
        $collection = Mage::getModel('blog/post')->getCollection();
        if (!$collection instanceof Maho_Blog_Model_Resource_Post_Collection) {
            return $this;
        }

        // Static attributes are automatically selected from main table
        // Only need to select EAV attributes explicitly
        $collection->addAttributeToSelect('image');

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

        $this->addColumn('url_key', [
            'header' => Mage::helper('blog')->__('URL Key'),
            'align' => 'left',
            'index' => 'url_key',
            'width' => '150px',
        ]);

        $this->addColumn('image', [
            'header' => Mage::helper('blog')->__('Image'),
            'align' => 'center',
            'index' => 'image',
            'width' => '80px',
            'sortable' => false,
            'filter' => false,
            'renderer' => 'blog/adminhtml_post_grid_renderer_image',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('stores', [
                'header'        => Mage::helper('blog')->__('Store View'),
                'index'         => 'stores',
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
            'width'     => '80px',
            'options'   => [
                0 => Mage::helper('blog')->__('Disabled'),
                1 => Mage::helper('blog')->__('Enabled'),
            ],
        ]);

        $this->addColumn('publish_date', [
            'header' => Mage::helper('blog')->__('Publishing Date'),
            'index'  => 'publish_date',
            'width' => '150px',
            'type' => 'date',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('blog')->__('Created At'),
            'index'  => 'created_at',
            'width' => '150px',
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
    protected function _prepareMassaction(): self
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
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }

    protected function _filterStoreCondition(Maho_Blog_Model_Resource_Post_Collection $collection, Mage_Adminhtml_Block_Widget_Grid_Column $column): self
    {
        $value = $column->getFilter()->getValue();
        if ($value) {
            $collection->addStoreFilter($value);
        }
        return $this;
    }

}
