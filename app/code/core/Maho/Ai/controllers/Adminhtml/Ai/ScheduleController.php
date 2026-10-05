<?php

/**
 * The scheduled assistant tasks: grid, edit form, pause, resume, run now and delete.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Adminhtml_Ai_ScheduleController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/ai/schedules';

    #[Maho\Config\Route('/admin/ai_schedule/index')]
    public function indexAction(): void
    {
        $this->_title(Mage::helper('ai')->__('Scheduled Tasks'));
        $this->loadLayout()
            ->_setActiveMenu('system/ai/schedules')
            ->_addBreadcrumb(Mage::helper('ai')->__('AI'), Mage::helper('ai')->__('AI'))
            ->_addBreadcrumb(Mage::helper('ai')->__('Scheduled Tasks'), Mage::helper('ai')->__('Scheduled Tasks'))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/ai_schedule/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    #[Maho\Config\Route('/admin/ai_schedule/edit')]
    public function editAction(): void
    {
        $helper = Mage::helper('ai');
        /** @var Maho_Ai_Model_Task_Schedule $schedule */
        $schedule = Mage::getModel('ai/task_schedule');
        $id = (int) $this->getRequest()->getParam('id');
        if ($id > 0) {
            $schedule->load($id);
            if (!$schedule->getId()) {
                Mage::getSingleton('adminhtml/session')->addError($helper->__('This scheduled task no longer exists.'));
                $this->_redirect('*/*/index');
                return;
            }
        }
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (is_array($data) && $data !== []) {
            $schedule->addData($data);
        }
        Mage::register('ai_task_schedule', $schedule);

        $this->_title($helper->__('Scheduled Tasks'))->_title($schedule->getId() ? (string) $schedule->getTitle() : $helper->__('New Scheduled Task'));
        $this->loadLayout()
            ->_setActiveMenu('system/ai/schedules')
            ->_addBreadcrumb($helper->__('AI'), $helper->__('AI'))
            ->_addBreadcrumb($helper->__('Scheduled Tasks'), $helper->__('Scheduled Tasks'))
            ->_addContent($this->getLayout()->createBlock('ai/adminhtml_schedule_edit'))
            ->renderLayout();
    }

    /** Whoever saves the task becomes its owner: the runs use the permissions of the owner. */
    #[Maho\Config\Route('/admin/ai_schedule/save', methods: ['POST'])]
    public function saveAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $request = $this->getRequest();
        /** @var Maho_Ai_Model_Task_Schedule $schedule */
        $schedule = Mage::getModel('ai/task_schedule');
        $id = (int) $request->getParam('id');
        if ($id > 0 && !$schedule->load($id)->getId()) {
            $session->addError(Mage::helper('ai')->__('This scheduled task no longer exists.'));
            $this->_redirect('*/*/index');
            return;
        }
        $data = [
            'title' => (string) $request->getPost('title'),
            'instruction' => (string) $request->getPost('instruction'),
            'cron_expr' => (string) $request->getPost('cron_expr'),
            'notify' => (string) $request->getPost('notify'),
        ];
        try {
            $schedule->addData($data)
                ->setIsActive((bool) $request->getPost('is_active'))
                ->validateFor(Mage::getSingleton('admin/session')->getUser())
                ->save();
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            $session->setFormData($data + ['is_active' => (int) (bool) $request->getPost('is_active')]);
            $this->_redirect('*/*/edit', $id > 0 ? ['id' => $id] : []);
            return;
        }
        $session->addSuccess(Mage::helper('ai')->__('The scheduled task was saved.'));
        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/ai_schedule/delete')]
    public function deleteAction(): void
    {
        $schedule = Mage::getModel('ai/task_schedule')->load((int) $this->getRequest()->getParam('id'));
        if ($schedule->getId()) {
            $schedule->delete();
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('ai')->__('The scheduled task was deleted.'));
        }
        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/ai_schedule/massStatus', methods: ['POST'])]
    public function massStatusAction(): void
    {
        $active = (bool) $this->getRequest()->getParam('status');
        $changed = 0;
        foreach ($this->selectedSchedules() as $schedule) {
            $schedule->setIsActive($active)->save();
            $changed++;
        }
        Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('ai')->__('%d scheduled task(s) were updated.', $changed));
        $this->_redirect('*/*/index');
    }

    /** A run uses the permissions of the administrator who created the task, so only that administrator starts one by hand. */
    #[Maho\Config\Route('/admin/ai_schedule/massRun', methods: ['POST'])]
    public function massRunAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $adminId = (int) Mage::getSingleton('admin/session')->getUser()->getId();
        $started = 0;
        $skipped = 0;
        foreach ($this->selectedSchedules() as $schedule) {
            if ($schedule->getAdminUserId() !== $adminId || $schedule->run() === null) {
                $skipped++;
                continue;
            }
            $started++;
        }
        $session->addSuccess(Mage::helper('ai')->__('%d scheduled task(s) were started. Each run is a new conversation in the assistant.', $started));
        if ($skipped > 0) {
            $session->addNotice(Mage::helper('ai')->__('%d scheduled task(s) were skipped: they belong to another administrator, or their previous run still runs or waits for a confirmation.', $skipped));
        }
        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/ai_schedule/massDelete', methods: ['POST'])]
    public function massDeleteAction(): void
    {
        $deleted = 0;
        foreach ($this->selectedSchedules() as $schedule) {
            $schedule->delete();
            $deleted++;
        }
        Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('ai')->__('%d scheduled task(s) were deleted.', $deleted));
        $this->_redirect('*/*/index');
    }

    /**
     * @return list<Maho_Ai_Model_Task_Schedule>
     */
    private function selectedSchedules(): array
    {
        $ids = array_map(intval(...), (array) $this->getRequest()->getParam('schedule_id', []));
        if ($ids === []) {
            return [];
        }
        /** @var Maho_Ai_Model_Resource_Task_Schedule_Collection $collection */
        $collection = Mage::getResourceModel('ai/task_schedule_collection');
        $collection->addFieldToFilter('schedule_id', ['in' => $ids]);

        return array_values($collection->getItems());
    }
}
