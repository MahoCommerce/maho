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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customer_segment_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('customersegmentation')->__('Segment Information'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('general', [
            'label'     => Mage::helper('customersegmentation')->__('General Properties'),
            'title'     => Mage::helper('customersegmentation')->__('General Properties'),
            'content'   => $this->getLayout()->createBlock('customersegmentation/adminhtml_segment_edit_tab_general')->toHtml(),
        ]);

        $this->addTab('conditions', [
            'label'     => Mage::helper('customersegmentation')->__('Conditions'),
            'title'     => Mage::helper('customersegmentation')->__('Conditions'),
            'content'   => $this->getLayout()->createBlock('customersegmentation/adminhtml_segment_edit_tab_conditions')->toHtml(),
        ]);

        $segment = Mage::registry('current_customer_segment');
        if ($segment && $segment->getId()) {
            $customerCount = (int) $segment->getMatchedCustomersCount();
            $this->addTab('customers', [
                'label'     => Mage::helper('customersegmentation')->__('Customers') . ($customerCount ? ' (' . $customerCount . ')' : ''),
                'title'     => Mage::helper('customersegmentation')->__('View Customers in Segment'),
                'url'       => $this->getUrl('*/*/customersTab', ['_current' => true]),
                'class'     => 'ajax',
            ]);
        }

        $this->addTab('email_automation', [
            'label'     => Mage::helper('customersegmentation')->__('Email Automation'),
            'title'     => Mage::helper('customersegmentation')->__('Email Automation Settings'),
            'content'   => $this->getLayout()->createBlock('customersegmentation/adminhtml_segment_edit_tab_emailAutomation')->toHtml(),
        ]);

        if ($segment && $segment->getId()) {

            // Add Email Automation - Enter Segment tab
            $enterSequences = Mage::getResourceModel('customersegmentation/emailSequence_collection')
                ->addFieldToFilter('segment_id', $segment->getId())
                ->addFieldToFilter('trigger_event', Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_ENTER);
            $enterCount = $enterSequences->getSize();

            $this->addTab('email_sequences_enter', [
                'label'     => Mage::helper('customersegmentation')->__('E-mails on Enter') . ($enterCount ? ' (' . $enterCount . ')' : ''),
                'title'     => Mage::helper('customersegmentation')->__('E-mails on Enter'),
                'url'       => $this->getUrl('*/*/sequencesGridEnter', ['_current' => true]),
                'class'     => 'ajax',
            ]);

            // Add Email Automation - Exit Segment tab
            $exitSequences = Mage::getResourceModel('customersegmentation/emailSequence_collection')
                ->addFieldToFilter('segment_id', $segment->getId())
                ->addFieldToFilter('trigger_event', Maho_CustomerSegmentation_Model_EmailSequence::TRIGGER_EXIT);
            $exitCount = $exitSequences->getSize();

            $this->addTab('email_sequences_exit', [
                'label'     => Mage::helper('customersegmentation')->__('E-mails on Exit') . ($exitCount ? ' (' . $exitCount . ')' : ''),
                'title'     => Mage::helper('customersegmentation')->__('E-mails on Exit'),
                'url'       => $this->getUrl('*/*/sequencesGridExit', ['_current' => true]),
                'class'     => 'ajax',
            ]);
        }

        return parent::_beforeToHtml();
    }
}
