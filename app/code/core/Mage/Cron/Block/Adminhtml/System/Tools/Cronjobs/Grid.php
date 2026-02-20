<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Block_Adminhtml_System_Tools_Cronjobs_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('cronjobsGrid');
        $this->setDefaultSort('schedule_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getModel('cron/schedule')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('schedule_id', [
            'header'    => Mage::helper('cron')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'schedule_id',
            'type'      => 'number',
        ]);

        $this->addColumn('job_code', [
            'header'    => Mage::helper('cron')->__('Job Code'),
            'align'     => 'left',
            'index'     => 'job_code',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('cron')->__('Status'),
            'align'     => 'left',
            'index'     => 'status',
            'type'      => 'options',
            'options'   => [
                Mage_Cron_Model_Schedule::STATUS_PENDING => Mage::helper('cron')->__('Pending'),
                Mage_Cron_Model_Schedule::STATUS_RUNNING => Mage::helper('cron')->__('Running'),
                Mage_Cron_Model_Schedule::STATUS_SUCCESS => Mage::helper('cron')->__('Success'),
                Mage_Cron_Model_Schedule::STATUS_MISSED  => Mage::helper('cron')->__('Missed'),
                Mage_Cron_Model_Schedule::STATUS_ERROR   => Mage::helper('cron')->__('Error'),
            ],
            'frame_callback' => [$this, 'decorateStatus'],
        ]);

        $this->addColumn('messages', [
            'header'    => Mage::helper('cron')->__('Messages'),
            'align'     => 'left',
            'index'     => 'messages',
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('cron')->__('Created At'),
            'align'     => 'left',
            'index'     => 'created_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('scheduled_at', [
            'header'    => Mage::helper('cron')->__('Scheduled At'),
            'align'     => 'left',
            'index'     => 'scheduled_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('executed_at', [
            'header'    => Mage::helper('cron')->__('Executed At'),
            'align'     => 'left',
            'index'     => 'executed_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('finished_at', [
            'header'    => Mage::helper('cron')->__('Finished At'),
            'align'     => 'left',
            'index'     => 'finished_at',
            'type'      => 'datetime',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('schedule_id');
        $this->getMassactionBlock()->setFormFieldName('schedule_ids');

        $this->getMassactionBlock()->addItem('delete', [
            'label'    => Mage::helper('cron')->__('Delete'),
            'url'      => $this->getUrl('*/*/massDelete'),
            'confirm'  => Mage::helper('cron')->__('Are you sure?'),
        ]);

        return $this;
    }

    public function decorateStatus(string $value, Mage_Cron_Model_Schedule $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        if ($isExport) {
            return $value;
        }

        $status = $row->getStatus();
        $class = '';

        $class = match ($status) {
            'running' => 'major',
            'missed', 'error' => 'critical',
            default => 'notice',
        };

        return '<span class="grid-severity-' . $class . '"><span>' . $value . '</span></span>';
    }
}
