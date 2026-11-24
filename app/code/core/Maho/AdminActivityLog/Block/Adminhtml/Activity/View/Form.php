<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_View_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $activity = Mage::registry('current_activity');
        $groupActivities = $activity->getGroupActivities();

        $form = new \Maho\Data\Form([
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

        $fieldset->addField('ip_address', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('IP Address'),
            'value' => $activity->getIpAddress(),
        ]);

        $fieldset->addField('request_url', 'label', [
            'label' => Mage::helper('adminactivitylog')->__('Request URL'),
            'value' => $activity->getRequestUrl(),
        ]);

        // If this is part of a group, show group summary
        if ($groupActivities->count() > 1) {
            $fieldset->addField('group_summary', 'note', [
                'label' => Mage::helper('adminactivitylog')->__('Action Group'),
                'text' => '<strong>' . $groupActivities->count() . ' activities in this action</strong>',
            ]);
        }

        // Show all activities in the group
        $activityIndex = 0;
        foreach ($groupActivities as $groupActivity) {
            $activityIndex++;

            $activityFieldset = $form->addFieldset('activity_' . $groupActivity->getId(), [
                'legend' => Mage::helper('adminactivitylog')->__(
                    'Activity #%d: %s %s',
                    $activityIndex,
                    ucfirst(str_replace('_', ' ', $groupActivity->getActionType())),
                    $groupActivity->getEntityType(),
                ),
            ]);

            $activityFieldset->addField('activity_' . $groupActivity->getId() . '_entity', 'note', [
                'label' => Mage::helper('adminactivitylog')->__('Entity'),
                'text' => '<strong>' . $this->escapeHtml($groupActivity->getEntityName()) . '</strong>'
                         . ($groupActivity->getEntityId() ? ' (ID: ' . $groupActivity->getEntityId() . ')' : ''),
            ]);

            $oldData = $groupActivity->getActionType() === 'create' ? [] : $groupActivity->getOldData();
            $newData = $groupActivity->getNewData();
            $changesHtml = '';
            foreach (array_unique(array_merge(array_keys($oldData), array_keys($newData))) as $field) {
                $oldValue = $this->formatValue($oldData[$field] ?? '');
                $newValue = $this->formatValue($newData[$field] ?? '');
                if ($oldValue === '' && $newValue === '') {
                    continue;
                }
                $changesHtml .= '<div style="margin-bottom: 1rem;"><strong>' . $this->escapeHtml($field) . ':</strong>'
                    . $this->getLayout()->createBlock('adminhtml/template')->setTemplate('widget/diff.phtml')->addData([
                        'old' => $oldValue,
                        'new' => $newValue,
                        'old_label' => Mage::helper('adminactivitylog')->__('Before'),
                        'new_label' => Mage::helper('adminactivitylog')->__('After'),
                    ])->toHtml()
                    . '</div>';
            }

            if ($changesHtml !== '') {
                $activityFieldset->addField('activity_' . $groupActivity->getId() . '_changes', 'note', [
                    'label' => Mage::helper('adminactivitylog')->__('Changes'),
                    'text' => $changesHtml,
                ]);
            }
        }

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function formatValue(mixed $value): string
    {
        return is_array($value) ? Mage::helper('core')->jsonEncode($value) : (string) $value;
    }
}
