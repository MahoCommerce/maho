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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Sequence_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'customersegmentation';
        $this->_controller = 'adminhtml_segment_sequence';

        parent::__construct();

        $segment = Mage::registry('current_customer_segment');
        $sequence = Mage::registry('current_email_sequence');
        $segmentId = $segment ? $segment->getId() : null;

        // Remove confusing default buttons
        $this->_removeButton('back');
        $this->_removeButton('reset');
        $this->_removeButton('save');

        // Add clear back button (left side - higher level = further left)
        $this->_addButton('back_to_segment', [
            'label'     => Mage::helper('customersegmentation')->__('Back to Segment'),
            'onclick'   => "setLocation('" . $this->getUrl('*/*/edit', ['id' => $segmentId, 'tab' => 'email_sequences']) . "')",
            'class'     => 'back',
        ], -1000);

        // Update delete button with segment_id parameter (right side)
        if ($sequence && $sequence->getId()) {
            $this->_updateButton('delete', 'label', Mage::helper('customersegmentation')->__('Delete Step'));
            $this->_updateButton('delete', 'onclick', "deleteConfirm('" .
                Mage::helper('customersegmentation')->__('Are you sure you want to delete this sequence step?') .
                "', '" .
                $this->getUrl('*/*/deleteSequence', ['id' => $sequence->getId(), 'segment_id' => $segmentId]) .
                "')");
            $this->_updateButton('delete', 'level', -10);
        } else {
            $this->_removeButton('delete');
        }

        // Add single save button that redirects back to segment (right side - lower level = further right)
        $this->_addButton('save', [
            'label'     => Mage::helper('customersegmentation')->__('Save'),
            'onclick'   => 'editForm.submit()',
            'class'     => 'save',
        ], 1000);
    }

    #[\Override]
    public function getHeaderText()
    {
        $sequence = Mage::registry('current_email_sequence');
        $segment = Mage::registry('current_customer_segment');

        $segmentName = ($segment && $segment->getId()) ? $segment->getName() : Mage::helper('customersegmentation')->__('Unknown Segment');

        if ($sequence->getId()) {
            return Mage::helper('customersegmentation')->__(
                'Edit Email Sequence Step %d for "%s"',
                $sequence->getStepNumber(),
                $segmentName,
            );
        }
        return Mage::helper('customersegmentation')->__(
            'New Email Sequence Step for "%s"',
            $segmentName,
        );
    }
}
