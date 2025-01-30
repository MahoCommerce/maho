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

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('blog/post')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

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

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
