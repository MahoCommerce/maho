<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs extends Mage_Adminhtml_Block_Widget_Grid
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setId('feedLogsGrid');
        $this->setDefaultSort('started_at');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $feed = $this->_getFeed();
        $collection = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter((int) $feed->getId());

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('log_id', [
            'header' => $this->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'log_id',
        ]);

        $this->addColumn('status', [
            'header' => $this->__('Status'),
            'index' => 'status',
            'width' => '100px',
            'type' => 'options',
            'options' => [
                Maho_FeedManager_Model_Log::STATUS_RUNNING => $this->__('Running'),
                Maho_FeedManager_Model_Log::STATUS_COMPLETED => $this->__('Completed'),
                Maho_FeedManager_Model_Log::STATUS_FAILED => $this->__('Failed'),
            ],
        ]);

        $this->addColumn('started_at', [
            'header' => $this->__('Started'),
            'index' => 'started_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);

        $this->addColumn('completed_at', [
            'header' => $this->__('Completed'),
            'index' => 'completed_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);

        $this->addColumn('product_count', [
            'header' => $this->__('Products'),
            'index' => 'product_count',
            'width' => '80px',
            'align' => 'right',
        ]);

        $this->addColumn('file_size', [
            'header' => $this->__('File Size'),
            'index' => 'file_size',
            'width' => '100px',
            'renderer' => Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Filesize::class,
        ]);

        $this->addColumn('upload_status', [
            'header' => $this->__('Upload'),
            'index' => 'upload_status',
            'width' => '120px',
            'renderer' => Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Upload::class,
        ]);

        $this->addColumn('errors', [
            'header' => $this->__('Errors'),
            'index' => 'errors',
            'renderer' => Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Logs_Renderer_Errors::class,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/logsGrid', ['_current' => true]);
    }
}
