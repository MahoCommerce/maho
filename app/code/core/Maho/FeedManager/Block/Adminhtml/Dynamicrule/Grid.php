<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('feedmanagerDynamicruleGrid');
        $this->setDefaultSort('rule_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('feedmanager/dynamicRule_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('rule_id', [
            'header' => $this->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'rule_id',
        ]);

        $this->addColumn('name', [
            'header' => $this->__('Name'),
            'index' => 'name',
        ]);

        $this->addColumn('code', [
            'header' => $this->__('Code'),
            'index' => 'code',
            'width' => '180px',
        ]);

        $this->addColumn('is_system', [
            'header' => $this->__('Type'),
            'index' => 'is_system',
            'width' => '100px',
            'type' => 'options',
            'options' => [
                1 => $this->__('System'),
                0 => $this->__('Custom'),
            ],
            'frame_callback' => [$this, 'decorateType'],
        ]);

        $this->addColumn('is_enabled', [
            'header' => $this->__('Status'),
            'index' => 'is_enabled',
            'width' => '100px',
            'type' => 'options',
            'options' => [
                1 => $this->__('Enabled'),
                0 => $this->__('Disabled'),
            ],
            'frame_callback' => [$this, 'decorateStatus'],
        ]);

        $this->addColumn('updated_at', [
            'header' => $this->__('Updated'),
            'index' => 'updated_at',
            'width' => '150px',
            'type' => 'datetime',
        ]);

        $this->addColumn('action', [
            'header' => $this->__('Action'),
            'width' => '80px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => $this->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
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
        $this->setMassactionIdField('rule_id');
        $this->getMassactionBlock()->setFormFieldName('rule_ids');

        $this->getMassactionBlock()->addItem('status', [
            'label' => $this->__('Change Status'),
            'url' => $this->getUrl('*/*/massStatus'),
            'additional' => [
                'status' => [
                    'name' => 'status',
                    'type' => 'select',
                    'class' => 'required-entry',
                    'label' => $this->__('Status'),
                    'values' => [
                        1 => $this->__('Enabled'),
                        0 => $this->__('Disabled'),
                    ],
                ],
            ],
        ]);

        $this->getMassactionBlock()->addItem('delete', [
            'label' => $this->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => $this->__('Are you sure you want to delete the selected rules? System rules cannot be deleted.'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }

    /**
     * Decorate type column
     */
    public function decorateType(string $value, Maho_FeedManager_Model_DynamicRule $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        if ($isExport) {
            return $value;
        }

        $class = $row->getIsSystem() ? 'fm-status-system' : 'fm-status-custom';
        return '<span class="' . $class . '">' . $this->escapeHtml($value) . '</span>';
    }

    /**
     * Decorate status column
     */
    public function decorateStatus(string $value, Maho_FeedManager_Model_DynamicRule $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        if ($isExport) {
            return $value;
        }

        $class = $row->getIsEnabled() ? 'fm-status-success' : 'fm-status-disabled';
        return '<span class="' . $class . '">' . $this->escapeHtml($value) . '</span>';
    }
}
