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

                    // Skip fields where both values are N/A or empty
                    if (($oldValue === 'N/A' || $oldValue === '') && ($newValue === 'N/A' || $newValue === '')) {
                        continue;
                    }

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
        // Convert N/A to empty string for better display
        if ($oldValue === 'N/A') $oldValue = '';
        if ($newValue === 'N/A') $newValue = '';
        
        // If values are identical, just show the value
        if ($oldValue === $newValue) {
            return '<div>' . ($oldValue ?: '<em>(empty)</em>') . '</div>';
        }


        // Always use the same diff format for consistency
        $html = '<div style="font-family: monospace; font-size: 12px;">';
        $html .= '<div style="background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">';

        // For single-line values, try to highlight just the differences
        if (strpos($oldValue, "\n") === false && strpos($newValue, "\n") === false) {
            // For single-line changes, try to show inline diff if possible
            if (strlen($oldValue) < 1000 && strlen($newValue) < 1000) {
                // Simple case: if new value contains old value, highlight the addition
                if (strpos($newValue, $oldValue) === 0) {
                    // Addition at the end
                    $added = substr($newValue, strlen($oldValue));
                    $html .= '<div style="margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">';
                    $html .= $this->escapeHtml($oldValue);
                    $html .= '<span style="background-color: #ddffdd; color: #008800;">' . $this->escapeHtml($added) . '</span>';
                    $html .= '</div>';
                } elseif (strpos($oldValue, $newValue) === 0) {
                    // Removal at the end
                    $removed = substr($oldValue, strlen($newValue));
                    $html .= '<div style="margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">';
                    $html .= $this->escapeHtml($newValue);
                    $html .= '<span style="background-color: #ffdddd; color: #cc0000; text-decoration: line-through;">' . $this->escapeHtml($removed) . '</span>';
                    $html .= '</div>';
                } else {
                    // Different values, show as removed/added
                    $html .= '<div style="background-color: #ffdddd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">- ' . $this->escapeHtml($oldValue) . '</div>';
                    $html .= '<div style="background-color: #ddffdd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">+ ' . $this->escapeHtml($newValue) . '</div>';
                }
            } else {
                // Too long, show as removed/added
                $html .= '<div style="background-color: #ffdddd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">- ' . $this->escapeHtml($oldValue) . '</div>';
                $html .= '<div style="background-color: #ddffdd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">+ ' . $this->escapeHtml($newValue) . '</div>';
            }
        } else {
            // Multi-line diff
            $oldLines = explode("\n", $oldValue);
            $newLines = explode("\n", $newValue);

            // Use a more sophisticated diff algorithm for multiline content
            $diff = $this->_calculateLineDiff($oldLines, $newLines);
            
            foreach ($diff as $line) {
                if ($line['type'] === 'removed') {
                    $html .= '<div style="background-color: #ffdddd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">- ' . $this->escapeHtml($line['content']) . '</div>';
                } elseif ($line['type'] === 'added') {
                    $html .= '<div style="background-color: #ddffdd; margin: 2px 0; padding: 2px 5px; white-space: pre-wrap; word-wrap: break-word;">+ ' . $this->escapeHtml($line['content']) . '</div>';
                } else {
                    $html .= '<div style="margin: 2px 0; padding: 2px 5px; color: #666; white-space: pre-wrap; word-wrap: break-word;">' . $this->escapeHtml($line['content']) . '</div>';
                }
            }
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    protected function _calculateLineDiff(array $oldLines, array $newLines): array
    {
        $diff = [];
        $oldCount = count($oldLines);
        $newCount = count($newLines);
        $maxCount = max($oldCount, $newCount);
        
        // Simple longest common subsequence approach
        for ($i = 0; $i < $maxCount; $i++) {
            $oldLine = isset($oldLines[$i]) ? $oldLines[$i] : null;
            $newLine = isset($newLines[$i]) ? $newLines[$i] : null;
            
            if ($oldLine === null && $newLine !== null) {
                // Line added
                $diff[] = ['type' => 'added', 'content' => $newLine];
            } elseif ($oldLine !== null && $newLine === null) {
                // Line removed
                $diff[] = ['type' => 'removed', 'content' => $oldLine];
            } elseif ($oldLine === $newLine) {
                // Line unchanged
                $diff[] = ['type' => 'unchanged', 'content' => $oldLine];
            } else {
                // Check if lines are similar after trimming whitespace
                $oldTrimmed = trim($oldLine);
                $newTrimmed = trim($newLine);
                
                if ($oldTrimmed === $newTrimmed && $oldTrimmed !== '') {
                    // Lines are the same except for whitespace - treat as unchanged
                    $diff[] = ['type' => 'unchanged', 'content' => $oldLine];
                } else {
                    // Line changed - show as removed then added
                    $diff[] = ['type' => 'removed', 'content' => $oldLine];
                    $diff[] = ['type' => 'added', 'content' => $newLine];
                }
            }
        }
        
        return $diff;
    }
}
