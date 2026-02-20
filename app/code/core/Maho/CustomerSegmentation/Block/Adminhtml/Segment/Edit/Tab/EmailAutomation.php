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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailAutomation extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $model = Mage::registry('current_customer_segment');
        $form = new \Maho\Data\Form();
        $form->setUseContainer(false);

        $fieldset = $form->addFieldset('email_automation_fieldset', [
            'legend' => Mage::helper('customersegmentation')->__('Email Automation Settings'),
        ]);

        $fieldset->addField('auto_email_active', 'select', [
            'label'  => Mage::helper('customersegmentation')->__('Enable Email Automation'),
            'title'  => Mage::helper('customersegmentation')->__('Enable Email Automation'),
            'name'   => 'auto_email_active',
            'values' => [
                ['value' => 0, 'label' => Mage::helper('customersegmentation')->__('No')],
                ['value' => 1, 'label' => Mage::helper('customersegmentation')->__('Yes')],
            ],
            'note'   => Mage::helper('customersegmentation')->__('Master switch to enable/disable all email automation for this segment'),
        ]);

        $fieldset->addField('allow_overlapping_sequences', 'select', [
            'label'  => Mage::helper('customersegmentation')->__('Allow Overlapping Sequences'),
            'title'  => Mage::helper('customersegmentation')->__('Allow Overlapping Sequences'),
            'name'   => 'allow_overlapping_sequences',
            'values' => [
                ['value' => 0, 'label' => Mage::helper('customersegmentation')->__('No')],
                ['value' => 1, 'label' => Mage::helper('customersegmentation')->__('Yes')],
            ],
            'note'   => Mage::helper('customersegmentation')->__('Allow starting new sequences while existing ones are still active for the same customer'),
        ]);

        if ($model) {
            $form->setValues($model->getData());
        }
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('customersegmentation')->__('Email Automation');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('customersegmentation')->__('Email Automation Settings');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
