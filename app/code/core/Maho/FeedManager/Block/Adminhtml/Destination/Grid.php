<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Destination_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('feedmanagerDestinationGrid');
        $this->setDefaultSort('destination_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('feedmanager/destination_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('destination_id', [
            'header' => $this->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'destination_id',
        ]);

        $this->addColumn('name', [
            'header' => $this->__('Name'),
            'index' => 'name',
        ]);

        $this->addColumn('type', [
            'header' => $this->__('Type'),
            'index' => 'type',
            'width' => '150px',
            'renderer' => 'feedmanager/adminhtml_destination_grid_renderer_type',
            'filter_index' => 'type',
            'type' => 'options',
            'options' => Maho_FeedManager_Model_Destination::getTypeOptions(),
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

        $this->addColumn('last_upload_at', [
            'header' => $this->__('Last Upload'),
            'index' => 'last_upload_at',
            'width' => '130px',
            'renderer' => 'feedmanager/adminhtml_feed_grid_renderer_lastGenerated',
            'filter_index' => 'last_upload_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('last_upload_status', [
            'header' => $this->__('Upload Status'),
            'index' => 'last_upload_status',
            'width' => '110px',
            'renderer' => 'feedmanager/adminhtml_destination_grid_renderer_uploadStatus',
            'filter_index' => 'last_upload_status',
            'type' => 'options',
            'options' => [
                'success' => $this->__('Success'),
                'failed' => $this->__('Failed'),
            ],
        ]);

        $this->addColumn('action', [
            'header' => $this->__('Action'),
            'width' => '100px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => $this->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'id',
                ],
                [
                    'caption' => $this->__('Test'),
                    'url' => ['base' => '*/*/test'],
                    'field' => 'id',
                ],
            ],
            'filter' => false,
            'sortable' => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
