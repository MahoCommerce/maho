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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_EmailAutomation extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $segment = Mage::registry('current_customer_segment');

        $form = new Varien_Data_Form();
        $fieldset = $form->addFieldset('email_automation', [
            'legend' => Mage::helper('customersegmentation')->__('Email Automation Settings'),
        ]);

        $fieldset->addField('auto_email_active', 'select', [
            'name'     => 'auto_email_active',
            'label'    => Mage::helper('customersegmentation')->__('Enable Email Automation'),
            'title'    => Mage::helper('customersegmentation')->__('Enable Email Automation'),
            'values'   => Mage::getSingleton('adminhtml/system_config_source_yesno')->toOptionArray(),
            'value'    => $segment->getAutoEmailActive(),
            'note'     => Mage::helper('customersegmentation')->__('Enable automatic email sequences for this segment'),
        ]);

        $fieldset->addField('auto_email_trigger', 'select', [
            'name'     => 'auto_email_trigger',
            'label'    => Mage::helper('customersegmentation')->__('Trigger Event'),
            'title'    => Mage::helper('customersegmentation')->__('When to Send Emails'),
            'values'   => [
                ['value' => 'enter', 'label' => Mage::helper('customersegmentation')->__('When customer enters segment')],
                ['value' => 'exit', 'label' => Mage::helper('customersegmentation')->__('When customer exits segment')],
            ],
            'value'    => $segment->getAutoEmailTrigger() ?: 'enter',
            'note'     => Mage::helper('customersegmentation')->__('Choose when to trigger email sequences'),
        ]);

        $fieldset->addField('allow_overlapping_sequences', 'select', [
            'name'     => 'allow_overlapping_sequences',
            'label'    => Mage::helper('customersegmentation')->__('Allow Overlapping Sequences'),
            'title'    => Mage::helper('customersegmentation')->__('Allow Multiple Sequences'),
            'values'   => Mage::getSingleton('adminhtml/system_config_source_yesno')->toOptionArray(),
            'value'    => $segment->getAllowOverlappingSequences(),
            'note'     => Mage::helper('customersegmentation')->__('Allow starting new sequences while existing ones are still active for the same customer'),
        ]);

        // Add automation statistics if segment exists
        if ($segment->getId()) {
            try {
                $stats = $segment->getEmailAutomationStats();
                if (!empty($stats)) {
                    $fieldset->addField('automation_stats', 'note', [
                        'label' => Mage::helper('customersegmentation')->__('Statistics'),
                        'text'  => $this->getStatsHtml($stats),
                    ]);
                }
            } catch (Exception $e) {
                // Ignore statistics if there's an error (e.g., tables don't exist yet)
                Mage::log('Failed to load automation statistics: ' . $e->getMessage(), Mage::LOG_WARNING);
            }
        }

        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Format automation statistics for display
     */
    protected function getStatsHtml(array $stats): string
    {
        $html = '<div class="grid" style="border: 1px solid #ccc; padding: 10px;">';
        $html .= '<table class="data" style="width: 100%;">';
        $html .= '<thead><tr><th>' . $this->__('Metric') . '</th><th>' . $this->__('Count') . '</th></tr></thead>';
        $html .= '<tbody>';

        $labels = [
            'total' => $this->__('Total Emails'),
            'scheduled' => $this->__('Scheduled'),
            'sent' => $this->__('Sent'),
            'failed' => $this->__('Failed'),
            'skipped' => $this->__('Skipped'),
            'unique_customers' => $this->__('Unique Customers'),
        ];

        foreach ($labels as $key => $label) {
            $value = isset($stats[$key]) ? (int) $stats[$key] : 0;
            $html .= '<tr><td>' . $label . '</td><td>' . $value . '</td></tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
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
