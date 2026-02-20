<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailSequencesExit extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('email_sequences_exit_grid');
        $this->setDefaultSort('step_number');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $segment = Mage::registry('current_customer_segment');
        $collection = Mage::getResourceModel('customersegmentation/emailSequence_collection');

        if ($segment->getId()) {
            $collection->addFieldToFilter('segment_id', $segment->getId());
            $collection->addFieldToFilter('trigger_event', Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT);
        } else {
            // For new segments, show empty grid
            $collection->addFieldToFilter('segment_id', 0);
        }

        // Join with newsletter template data
        $newsletterResource = Mage::getResourceSingleton('newsletter/template');
        $collection->getSelect()->joinLeft(
            ['template' => $newsletterResource->getMainTable()],
            'main_table.template_id = template.template_id',
            ['template_code', 'template_subject'],
        );

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('step_number', [
            'header' => Mage::helper('customersegmentation')->__('Step'),
            'index' => 'step_number',
            'type' => 'number',
            'width' => '50px',
            'align' => 'center',
        ]);

        $this->addColumn('template_code', [
            'header' => Mage::helper('customersegmentation')->__('Email Template'),
            'index' => 'template_code',
            'width' => '200px',
        ]);

        $this->addColumn('template_subject', [
            'header' => Mage::helper('customersegmentation')->__('Subject'),
            'index' => 'template_subject',
            'width' => '250px',
        ]);

        $this->addColumn('delay_formatted', [
            'header' => Mage::helper('customersegmentation')->__('Delay'),
            'index' => 'delay_minutes',
            'renderer' => 'customersegmentation/adminhtml_segment_edit_tab_emailSequences_renderer_delay',
            'width' => '100px',
            'align' => 'center',
        ]);

        $this->addColumn('generate_coupon', [
            'header' => Mage::helper('customersegmentation')->__('Generate Coupon'),
            'index' => 'generate_coupon',
            'type' => 'options',
            'options' => [0 => Mage::helper('customersegmentation')->__('No'), 1 => Mage::helper('customersegmentation')->__('Yes')],
            'width' => '100px',
            'align' => 'center',
        ]);

        $this->addColumn('is_active', [
            'header' => Mage::helper('customersegmentation')->__('Active'),
            'index' => 'is_active',
            'type' => 'options',
            'options' => [0 => Mage::helper('customersegmentation')->__('No'), 1 => Mage::helper('customersegmentation')->__('Yes')],
            'width' => '70px',
            'align' => 'center',
        ]);

        $segment = Mage::registry('current_customer_segment');
        $this->addColumn('action', [
            'header' => Mage::helper('customersegmentation')->__('Action'),
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => Mage::helper('customersegmentation')->__('Edit'),
                    'url' => ['base' => '*/*/editSequence', 'params' => ['segment_id' => $segment->getId(), 'trigger_event' => 'exit']],
                    'field' => 'id',
                ],
                [
                    'caption' => Mage::helper('customersegmentation')->__('Delete'),
                    'url' => ['base' => '*/*/deleteSequence', 'params' => ['segment_id' => $segment->getId(), 'trigger_event' => 'exit']],
                    'field' => 'id',
                    'confirm' => Mage::helper('customersegmentation')->__('Are you sure you want to delete this sequence step?'),
                ],
            ],
            'filter' => false,
            'sortable' => false,
            'width' => '150px',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Add new sequence button
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        $segment = Mage::registry('current_customer_segment');
        if ($segment->getId()) {
            $this->setChild(
                'add_sequence_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setData([
                        'label'   => Mage::helper('customersegmentation')->__('Add Email Step'),
                        'onclick' => "setLocation('" . $this->getUrl('*/*/newSequence', ['segment_id' => $segment->getId(), 'trigger_event' => 'exit']) . "')",
                        'class'   => 'add',
                    ]),
            );
        }
        return $this;
    }

    /**
     * Get add new sequence button HTML
     */
    #[\Override]
    public function getMainButtonsHtml()
    {
        $html = parent::getMainButtonsHtml();
        if ($this->getChild('add_sequence_button')) {
            $html .= $this->getChild('add_sequence_button')->toHtml();
        }
        return $html;
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('customersegmentation')->__('E-mails on Exit');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('customersegmentation')->__('E-mails on Exit');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        $segment = Mage::registry('current_customer_segment');
        return $segment && $segment->getId(); // Only show for existing segments
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/sequencesGridExit', ['_current' => true]);
    }

    /**
     * Get row URL for editing
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/editSequence', ['id' => $row->getId(), 'trigger_event' => 'exit']);
    }
}
