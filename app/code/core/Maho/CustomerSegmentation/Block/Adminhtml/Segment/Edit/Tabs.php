<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

        // Add Email Automation tab
        $this->addTab('email_automation', [
            'label'     => Mage::helper('customersegmentation')->__('Email Automation'),
            'title'     => Mage::helper('customersegmentation')->__('Email Automation Settings'),
            'content'   => $this->getLayout()->createBlock('customersegmentation/adminhtml_segment_edit_tab_emailAutomation')->toHtml(),
        ]);

        // Add Email Sequences tab (only for existing segments)
        if ($segment && $segment->getId()) {
            $sequenceCount = $segment->getEmailSequences()->getSize();
            $this->addTab('email_sequences', [
                'label'     => Mage::helper('customersegmentation')->__('Email Sequences') . ($sequenceCount ? ' (' . $sequenceCount . ')' : ''),
                'title'     => Mage::helper('customersegmentation')->__('Manage Email Sequences'),
                'url'       => $this->getUrl('*/*/sequencesGrid', ['_current' => true]),
                'class'     => 'ajax',
            ]);
        }

        return parent::_beforeToHtml();
    }
}
