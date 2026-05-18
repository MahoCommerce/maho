<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Block_Adminhtml_Task_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('aiTaskGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $this->setCollection(Mage::getModel('ai/task')->getCollection());
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $helper = Mage::helper('ai');

        $this->addColumn('task_id', [
            'header' => $helper->__('ID'),
            'index'  => 'task_id',
            'width'  => '60px',
            'type'   => 'number',
        ]);

        $this->addColumn('consumer', [
            'header' => $helper->__('Consumer'),
            'index'  => 'consumer',
        ]);

        $this->addColumn('task_type', [
            'header'  => $helper->__('Type'),
            'index'   => 'task_type',
            'width'   => '100px',
            'type'    => 'options',
            'options' => [
                Maho_Ai_Model_Task::TYPE_COMPLETION => $helper->__('Completion'),
                Maho_Ai_Model_Task::TYPE_EMBEDDING  => $helper->__('Embedding'),
                Maho_Ai_Model_Task::TYPE_IMAGE      => $helper->__('Image'),
            ],
        ]);

        $this->addColumn('status', [
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'width'   => '100px',
            'type'    => 'options',
            'options' => [
                Maho_Ai_Model_Task::STATUS_PENDING    => $helper->__('Pending'),
                Maho_Ai_Model_Task::STATUS_PROCESSING => $helper->__('Processing'),
                Maho_Ai_Model_Task::STATUS_COMPLETE   => $helper->__('Complete'),
                Maho_Ai_Model_Task::STATUS_FAILED     => $helper->__('Failed'),
                Maho_Ai_Model_Task::STATUS_CANCELLED  => $helper->__('Cancelled'),
            ],
        ]);

        $this->addColumn('priority', [
            'header'  => $helper->__('Priority'),
            'index'   => 'priority',
            'width'   => '90px',
            'type'    => 'options',
            'options' => [
                Maho_Ai_Model_Task::PRIORITY_INTERACTIVE => $helper->__('Interactive'),
                Maho_Ai_Model_Task::PRIORITY_BACKGROUND  => $helper->__('Background'),
            ],
        ]);

        $this->addColumn('platform', [
            'header' => $helper->__('Platform'),
            'index'  => 'platform',
            'width'  => '90px',
        ]);

        $this->addColumn('model', [
            'header' => $helper->__('Model'),
            'index'  => 'model',
        ]);

        $this->addColumn('input_tokens', [
            'header' => $helper->__('In Tokens'),
            'index'  => 'input_tokens',
            'width'  => '80px',
            'type'   => 'number',
        ]);

        $this->addColumn('output_tokens', [
            'header' => $helper->__('Out Tokens'),
            'index'  => 'output_tokens',
            'width'  => '80px',
            'type'   => 'number',
        ]);

        $this->addColumn('created_at', [
            'header' => $helper->__('Queued'),
            'index'  => 'created_at',
            'width'  => '140px',
            'type'   => 'datetime',
        ]);

        $this->addColumn('completed_at', [
            'header' => $helper->__('Completed'),
            'index'  => 'completed_at',
            'width'  => '140px',
            'type'   => 'datetime',
        ]);

        $this->addColumn('action', [
            'header'    => $helper->__('Action'),
            'width'     => '80px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption' => $helper->__('View'),
                    'url'     => ['base' => '*/*/view'],
                    'field'   => 'id',
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }
}
