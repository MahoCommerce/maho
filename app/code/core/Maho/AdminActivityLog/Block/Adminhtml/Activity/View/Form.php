<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_View_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $activity = Mage::registry('current_activity');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save'),
            'method' => 'post',
        ]);

        $fieldset = $form->addFieldset('activity_info', ['legend' => Mage::helper('adminactivitylog')->__('Activity Information')]);

        $fieldset->addField('activity_id', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Activity ID'),
            'value' => $activity->getId(),
        ]);

        $fieldset->addField('created_at', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Date/Time'),
            'value' => Mage::helper('core')->formatDate($activity->getCreatedAt(), 'medium', true),
        ]);

        $fieldset->addField('username', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Username'),
            'value' => $activity->getUsername(),
        ]);

        $fieldset->addField('fullname', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Full Name'),
            'value' => $activity->getFullname(),
        ]);

        $fieldset->addField('action_type', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Action Type'),
            'value' => ucfirst(str_replace('_', ' ', $activity->getActionType())),
        ]);

        $fieldset->addField('entity_type', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Entity Type'),
            'value' => $activity->getEntityType(),
        ]);

        $fieldset->addField('entity_id', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Entity ID'),
            'value' => $activity->getEntityId(),
        ]);

        $fieldset->addField('entity_name', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Entity Name'),
            'value' => $activity->getEntityName(),
        ]);

        $fieldset->addField('ip_address', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('IP Address'),
            'value' => $activity->getIpAddress(),
        ]);

        $fieldset->addField('request_url', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Request URL'),
            'value' => $activity->getRequestUrl(),
        ]);

        if ($activity->getOldData() || $activity->getNewData()) {
            $changesFieldset = $form->addFieldset('activity_changes', ['legend' => Mage::helper('adminactivitylog')->__('Data Changes')]);

            $oldData = $activity->getOldData() ? json_decode($activity->getOldData(), true) : [];
            $newData = $activity->getNewData() ? json_decode($activity->getNewData(), true) : [];

            // For updates, the data already contains only changed fields
            // For creates, show all new data
            if ($activity->getActionType() === 'create') {
                $changedFields = [];
                foreach ($newData as $key => $newValue) {
                    $changedFields[$key] = [
                        'old' => 'N/A',
                        'new' => $newValue,
                    ];
                }
            } else {
                // For updates, combine old and new data to show changes
                $changedFields = [];
                $allChangedFields = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

                foreach ($allChangedFields as $key) {
                    $changedFields[$key] = [
                        'old' => isset($oldData[$key]) ? $oldData[$key] : 'N/A',
                        'new' => isset($newData[$key]) ? $newData[$key] : 'N/A',
                    ];
                }
            }

            if (!empty($changedFields)) {
                foreach ($changedFields as $field => $values) {
                    $oldValue = is_array($values['old']) ? json_encode($values['old']) : (string) $values['old'];
                    $newValue = is_array($values['new']) ? json_encode($values['new']) : (string) $values['new'];

                    // Generate diff HTML
                    $diffHtml = $this->_generateDiffHtml($oldValue, $newValue);

                    $changesFieldset->addField('change_' . $field, 'note', [
                        'label' => $this->escapeHtml($field),
                        'text' => $diffHtml,
                    ]);
                }
            }
        }

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _generateDiffHtml(string $oldValue, string $newValue): string
    {
        // If values are identical, just show the value
        if ($oldValue === $newValue) {
            return '<div>' . $this->escapeHtml($oldValue) . '</div>';
        }


        // Always use the same diff format for consistency
        $html = '<div style="font-family: monospace; font-size: 12px;">';
        $html .= '<div style="background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">';

        $oldLines = explode("\n", $oldValue);
        $newLines = explode("\n", $newValue);

        // Simple line diff
        $maxLines = max(count($oldLines), count($newLines));
        for ($i = 0; $i < $maxLines; $i++) {
            $oldLine = isset($oldLines[$i]) ? $oldLines[$i] : '';
            $newLine = isset($newLines[$i]) ? $newLines[$i] : '';

            if ($oldLine !== $newLine) {
                if ($oldLine && !$newLine) {
                    // Line removed
                    $html .= '<div style="background-color: #ffdddd; margin: 2px 0; padding: 2px 5px;">- ' . $this->escapeHtml($oldLine) . '</div>';
                } elseif (!$oldLine && $newLine) {
                    // Line added
                    $html .= '<div style="background-color: #ddffdd; margin: 2px 0; padding: 2px 5px;">+ ' . $this->escapeHtml($newLine) . '</div>';
                } else {
                    // Line changed
                    $html .= '<div style="background-color: #ffdddd; margin: 2px 0; padding: 2px 5px;">- ' . $this->escapeHtml($oldLine) . '</div>';
                    $html .= '<div style="background-color: #ddffdd; margin: 2px 0; padding: 2px 5px;">+ ' . $this->escapeHtml($newLine) . '</div>';
                }
            } else {
                // Line unchanged
                $html .= '<div style="margin: 2px 0; padding: 2px 5px; color: #666;">&nbsp; ' . $this->escapeHtml($oldLine) . '</div>';
            }
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
