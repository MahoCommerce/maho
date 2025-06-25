<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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

        $fieldset->addField('entity_name', 'note', [
            'label' => Mage::helper('adminactivitylog')->__('Entity Name'),
            'text' => nl2br($this->escapeHtml($activity->getEntityName())),
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
                        'old' => $oldData[$key] ?? 'N/A',
                        'new' => $newData[$key] ?? 'N/A',
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
                    $diffHtml = $this->generateDiffHtml($oldValue, $newValue);

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

    protected function generateDiffHtml(string $oldValue, string $newValue): string
    {
        // Convert N/A to empty string for better display
        if ($oldValue === 'N/A') {
            $oldValue = '';
        }
        if ($newValue === 'N/A') {
            $newValue = '';
        }

        // If values are identical, just show the value
        if ($oldValue === $newValue) {
            return '<div style="white-space: pre-wrap; font-family: monospace;">' . ($oldValue ?: '<em>(empty)</em>') . '</div>';
        }


        // Always use the same diff format for consistency
        $html = '<div style="font-family: monospace; font-size: 12px;">';
        $html .= '<div style="background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">';

        // For single-line values, use the same grouped view as multiline
        if (!str_contains($oldValue, "\n") && !str_contains($newValue, "\n")) {
            // Single line diff - show as two groups (removed and added)
            if ($oldValue !== '') {
                $html .= '<div style="background-color: #ffdddd; margin: 4px 0; padding: 6px 8px; border-left: 3px solid #cc0000;">';
                $html .= '<div style="white-space: pre-wrap; word-wrap: break-word;">- ' . $this->escapeHtml($oldValue) . '</div>';
                $html .= '</div>';
            }
            if ($newValue !== '') {
                $html .= '<div style="background-color: #ddffdd; margin: 4px 0; padding: 6px 8px; border-left: 3px solid #008800;">';
                $html .= '<div style="white-space: pre-wrap; word-wrap: break-word;">+ ' . $this->escapeHtml($newValue) . '</div>';
                $html .= '</div>';
            }
        } else {
            // Multi-line diff
            $oldLines = explode("\n", $oldValue);
            $newLines = explode("\n", $newValue);

            // Use a more sophisticated diff algorithm for multiline content
            $diff = $this->calculateLineDiff($oldLines, $newLines);

            // Group consecutive lines of the same type
            $groups = [];
            $currentGroup = null;

            foreach ($diff as $line) {
                if ($currentGroup === null || $currentGroup['type'] !== $line['type']) {
                    if ($currentGroup !== null) {
                        $groups[] = $currentGroup;
                    }
                    $currentGroup = ['type' => $line['type'], 'lines' => []];
                }
                $currentGroup['lines'][] = $line['content'];
            }
            if ($currentGroup !== null) {
                $groups[] = $currentGroup;
            }

            // Render grouped lines
            foreach ($groups as $group) {
                if ($group['type'] === 'removed') {
                    $html .= '<div style="background-color: #ffdddd; margin: 4px 0; padding: 6px 8px; border-left: 3px solid #cc0000;">';
                    foreach ($group['lines'] as $line) {
                        $html .= '<div style="white-space: pre-wrap; word-wrap: break-word;">- ' . $this->escapeHtml($line) . '</div>';
                    }
                    $html .= '</div>';
                } elseif ($group['type'] === 'added') {
                    $html .= '<div style="background-color: #ddffdd; margin: 4px 0; padding: 6px 8px; border-left: 3px solid #008800;">';
                    foreach ($group['lines'] as $line) {
                        $html .= '<div style="white-space: pre-wrap; word-wrap: break-word;">+ ' . $this->escapeHtml($line) . '</div>';
                    }
                    $html .= '</div>';
                } else {
                    $html .= '<div style="margin: 4px 0; padding: 6px 8px; color: #666;">';
                    foreach ($group['lines'] as $line) {
                        $html .= '<div style="white-space: pre-wrap; word-wrap: break-word;">&nbsp;&nbsp;' . $this->escapeHtml($line) . '</div>';
                    }
                    $html .= '</div>';
                }
            }
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    protected function calculateLineDiff(array $oldLines, array $newLines): array
    {
        $diff = [];
        $oldCount = count($oldLines);
        $newCount = count($newLines);
        $maxCount = max($oldCount, $newCount);

        // Simple longest common subsequence approach
        for ($i = 0; $i < $maxCount; $i++) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

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
