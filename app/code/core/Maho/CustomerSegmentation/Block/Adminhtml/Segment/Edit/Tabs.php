<?php

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

        return parent::_beforeToHtml();
    }
}
