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

            $changedFields = [];
            foreach ($newData as $key => $newValue) {
                if (!isset($oldData[$key]) || $oldData[$key] != $newValue) {
                    $changedFields[$key] = [
                        'old' => isset($oldData[$key]) ? $oldData[$key] : 'N/A',
                        'new' => $newValue,
                    ];
                }
            }

            if (!empty($changedFields)) {
                $html = '<table class="form-list"><tbody>';
                foreach ($changedFields as $field => $values) {
                    $html .= '<tr>';
                    $html .= '<td class="label"><strong>' . $this->escapeHtml($field) . ':</strong></td>';
                    $html .= '<td class="value">';
                    $html .= '<div><strong>Old:</strong> ' . $this->escapeHtml(print_r($values['old'], true)) . '</div>';
                    $html .= '<div><strong>New:</strong> ' . $this->escapeHtml(print_r($values['new'], true)) . '</div>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';

                $changesFieldset->addField('changes_html', 'note', [
                    'text' => $html,
                ]);
            }
        }

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
