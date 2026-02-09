<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('feedmanagerFeedGrid');
        $this->setDefaultSort('feed_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('feedmanager/feed_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('feed_id', [
            'header' => $this->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'feed_id',
        ]);

        $this->addColumn('name', [
            'header' => $this->__('Feed Name'),
            'index' => 'name',
        ]);

        $this->addColumn('platform', [
            'header' => $this->__('Platform'),
            'index' => 'platform',
            'width' => '120px',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_platform',
            'filter_index' => 'platform',
            'type' => 'options',
            'options' => Maho_FeedManager_Model_Platform::getPlatformOptions(),
        ]);

        $this->addColumn('file_format', [
            'header' => $this->__('Format'),
            'index' => 'file_format',
            'width' => '80px',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_format',
            'filter_index' => 'file_format',
            'type' => 'options',
            'options' => Mage::helper('feedmanager')->getFileFormatOptions(),
        ]);

        $this->addColumn('store_id', [
            'header' => $this->__('Store'),
            'index' => 'store_id',
            'type' => 'store',
            'width' => '150px',
        ]);

        $this->addColumn('is_enabled', [
            'header' => $this->__('Status'),
            'index' => 'is_enabled',
            'width' => '100px',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_enabled',
            'filter_index' => 'is_enabled',
            'type' => 'options',
            'options' => [
                1 => $this->__('Enabled'),
                0 => $this->__('Disabled'),
            ],
        ]);

        $this->addColumn('last_generated_at', [
            'header' => $this->__('Last Generated'),
            'index' => 'last_generated_at',
            'width' => '130px',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_lastGenerated',
            'filter_index' => 'last_generated_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('last_product_count', [
            'header' => $this->__('Products'),
            'index' => 'last_product_count',
            'width' => '90px',
            'align' => 'right',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_productCount',
        ]);

        $this->addColumn('action', [
            'header' => $this->__('Action'),
            'width' => '150px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => $this->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'id',
                ],
                [
                    'caption' => $this->__('Generate'),
                    'url' => ['base' => '*/*/generate'],
                    'field' => 'id',
                    'confirm' => $this->__('Are you sure you want to generate this feed now?'),
                ],
                [
                    'caption' => $this->__('Download'),
                    'url' => ['base' => '*/*/download'],
                    'field' => 'id',
                ],
            ],
            'filter' => false,
            'sortable' => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('feed_id');
        $this->getMassactionBlock()->setFormFieldName('feed_ids');

        $this->getMassactionBlock()->addItem('generate', [
            'label' => $this->__('Generate'),
            'url' => $this->getUrl('*/*/massGenerate'),
            'confirm' => $this->__('Generate selected feeds?'),
        ]);

        $this->getMassactionBlock()->addItem('enable', [
            'label' => $this->__('Enable'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 1]),
        ]);

        $this->getMassactionBlock()->addItem('disable', [
            'label' => $this->__('Disable'),
            'url' => $this->getUrl('*/*/massStatus', ['status' => 0]),
        ]);

        $this->getMassactionBlock()->addItem('delete', [
            'label' => $this->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => $this->__('Are you sure you want to delete selected feeds?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
