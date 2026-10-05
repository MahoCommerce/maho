<?php

/**
 * Form of a scheduled assistant task.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Block_Adminhtml_Schedule_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $helper = Mage::helper('ai');
        /** @var Maho_Ai_Model_Task_Schedule $schedule */
        $schedule = Mage::registry('ai_task_schedule');
        $admin = Mage::getSingleton('admin/session')->getUser();

        $form = new \Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $schedule->getId()]),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => $helper->__('Scheduled Task')]);
        $fieldset->addField('title', 'text', [
            'name' => 'title',
            'label' => $helper->__('Title'),
            'required' => true,
            'maxlength' => 255,
        ]);
        $fieldset->addField('instruction', 'textarea', [
            'name' => 'instruction',
            'label' => $helper->__('Instruction'),
            'required' => true,
            'note' => $helper->__('What the assistant does in each run, as if to a colleague who cannot ask back: what to check, the limits, and when to notify. A run never changes data by itself: a change waits in the run conversation for your confirmation.'),
        ]);
        $fieldset->addField('cron_expr', 'text', [
            'name' => 'cron_expr',
            'label' => $helper->__('Schedule'),
            'required' => true,
            'note' => $helper->__('Five cron fields in the store time zone: minute, hour, day of the month, month, day of the week. "0 8 * * *" runs every day at 8:00, "30 9 * * 1" every Monday at 9:30. At most once an hour.'),
        ]);
        $fieldset->addField('notify', 'select', [
            'name' => 'notify',
            'label' => $helper->__('Notify'),
            'values' => Maho_Ai_Model_Task_Schedule::audienceOptions($admin),
            'note' => $helper->__('Who sees the notifications of the runs in the admin inbox. A failure or a change that waits for a confirmation always goes to the owner.'),
        ]);
        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => $helper->__('Status'),
            'values' => [1 => $helper->__('Active'), 0 => $helper->__('Paused')],
        ]);

        if ($schedule->getId()) {
            $owner = Mage::getModel('admin/user')->load((int) $schedule->getAdminUserId());
            $fieldset->addField('owner', 'note', [
                'label' => $helper->__('Owner'),
                'text' => $this->escapeHtml((string) ($owner->getUsername() ?? '')) . '<br><small>'
                    . $this->escapeHtml($helper->__('The runs use the permissions of the owner. You become the owner when you save.')) . '</small>',
            ]);
            foreach (['next_run_at' => $helper->__('Next Run'), 'last_run_at' => $helper->__('Last Run')] as $column => $label) {
                $value = $schedule->getData($column);
                $fieldset->addField($column, 'note', [
                    'label' => $label,
                    'text' => $value ? $this->escapeHtml(Mage::helper('core')->formatDate((string) $value, 'medium', true)) : '-',
                ]);
            }
        } else {
            $fieldset->addField('owner', 'note', [
                'label' => $helper->__('Owner'),
                'text' => $this->escapeHtml($helper->__('You. The runs use your permissions.')),
            ]);
        }

        $values = $schedule->getData();
        $values['is_active'] = $schedule->getIsActive() ?? true ? 1 : 0;
        $values['notify'] ??= Maho_Ai_Model_Task_Schedule::NOTIFY_SELF;
        unset($values['next_run_at'], $values['last_run_at']);
        $form->setValues($values);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
