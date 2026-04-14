<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Adminhtml_System_Tools_CronjobsController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/cronjobs';

    #[\Override]
    public function preDispatch(): self
    {
        $this->_setForcedFormKeyActions(['clearHistory', 'massDisable', 'massEnable', 'run', 'toggle']);
        return parent::preDispatch();
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/index')]

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('system/tools/cronjobs');
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('System'),
            Mage::helper('cron')->__('System'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Tools'),
            Mage::helper('cron')->__('Tools'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Cron Jobs'),
            Mage::helper('cron')->__('Cron Jobs'),
        );
        $this->_addContent($this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/grid')]

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_grid')->toHtml(),
        );
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/clearHistory')]

    public function clearHistoryAction(): void
    {
        try {
            $resource = Mage::getSingleton('core/resource');
            $adapter = $resource->getConnection('core_write');
            $table = $resource->getTableName('cron/schedule');
            $deleted = $adapter->delete($table, [
                'status IN (?)' => [
                    Mage_Cron_Model_Schedule::STATUS_SUCCESS,
                    Mage_Cron_Model_Schedule::STATUS_MISSED,
                    Mage_Cron_Model_Schedule::STATUS_ERROR,
                ],
            ]);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Cron history has been cleared. %d record(s) deleted.', $deleted),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/history')]

    public function historyAction(): void
    {
        $jobCode = $this->getRequest()->getParam('job_code');
        if (!$jobCode) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('No job code specified.')]);
            return;
        }

        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('cron/schedule');

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($table)
                ->where('job_code = ?', $jobCode)
                ->order('schedule_id DESC')
                ->limit(50),
        );

        $records = [];
        foreach ($rows as $row) {
            $duration = null;
            if ($row['executed_at'] && $row['finished_at']) {
                $duration = $helper->formatDuration(strtotime($row['finished_at']) - strtotime($row['executed_at']));
            }

            $records[] = [
                'schedule_id' => $row['schedule_id'],
                'status' => $row['status'],
                'messages' => $row['messages'] ?? '',
                'created_at' => $row['created_at'],
                'scheduled_at' => $row['scheduled_at'],
                'executed_at' => $row['executed_at'],
                'finished_at' => $row['finished_at'],
                'duration' => $duration,
            ];
        }

        $this->getResponse()->setBodyJson(['records' => $records]);
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/run')]

    public function runAction(): void
    {
        $jobCode = $this->getRequest()->getParam('job_code');
        if (!$jobCode) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('No job code specified.')]);
            return;
        }

        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');
        $jobs = $helper->getConfiguredJobs();
        if (!isset($jobs[$jobCode])) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Unknown cron job: %s', $jobCode)]);
            return;
        }

        $modelMethod = $jobs[$jobCode]['model_method'];
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, $modelMethod, $run)) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Invalid model/method definition, expecting "model/class::method".')]);
            return;
        }

        $model = Mage::getModel($run[1]);
        if (!$model || !method_exists($model, $run[2])) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Invalid callback: %s::%s does not exist', $run[1], $run[2])]);
            return;
        }

        $schedule = Mage::getModel('cron/schedule');
        $now = Mage_Core_Model_Locale::now();
        $schedule->setJobCode($jobCode)
            ->setStatus(Mage_Cron_Model_Schedule::STATUS_RUNNING)
            ->setCreatedAt($now)
            ->setScheduledAt($now)
            ->setExecutedAt($now)
            ->save();

        $scheduleId = $schedule->getId();

        // Send response immediately with schedule ID
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $json = Mage::helper('core')->jsonEncode(['schedule_id' => $scheduleId]);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
        echo $json;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }

        // Continue execution in background
        ignore_user_abort(true);
        set_time_limit(0);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            call_user_func([$model, $run[2]], $schedule);

            $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_SUCCESS)
                ->setFinishedAt(Mage_Core_Model_Locale::now())
                ->save();
        } catch (Exception $e) {
            $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                ->setMessages($e->getMessage())
                ->setFinishedAt(Mage_Core_Model_Locale::now())
                ->save();
        }

        // Prevent framework from sending another response
        exit;
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/runStatus')]

    public function runStatusAction(): void
    {
        $scheduleId = (int) $this->getRequest()->getParam('schedule_id');
        if (!$scheduleId) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('No schedule ID specified.')]);
            return;
        }

        $schedule = Mage::getModel('cron/schedule')->load($scheduleId);
        if (!$schedule->getId()) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Schedule not found.')]);
            return;
        }

        $status = $schedule->getStatus();
        $data = [
            'status' => $status,
            'finished' => in_array($status, [
                Mage_Cron_Model_Schedule::STATUS_SUCCESS,
                Mage_Cron_Model_Schedule::STATUS_ERROR,
                Mage_Cron_Model_Schedule::STATUS_MISSED,
            ], true),
        ];

        if ($data['finished']) {
            $data['executed_at'] = $schedule->getExecutedAt();
            $data['finished_at'] = $schedule->getFinishedAt();
            $data['messages'] = $schedule->getMessages();

            if ($schedule->getExecutedAt() && $schedule->getFinishedAt()) {
                $duration = strtotime($schedule->getFinishedAt()) - strtotime($schedule->getExecutedAt());
                /** @var Mage_Cron_Helper_Data $helper */
                $helper = Mage::helper('cron');
                $data['duration'] = $helper->formatDuration($duration);
            }
        }

        $this->getResponse()->setBodyJson($data);
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/toggle')]

    public function toggleAction(): void
    {
        $jobCode = $this->getRequest()->getParam('job_code');
        if (!$jobCode) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('No job code specified.'));
            $this->_redirect('*/*/index');
            return;
        }

        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');
        $isCurrentlyEnabled = $helper->isJobEnabled($jobCode);

        try {
            $helper->setJobEnabled($jobCode, !$isCurrentlyEnabled);
            if ($isCurrentlyEnabled) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Cron job "%s" has been disabled.', $jobCode),
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Cron job "%s" has been enabled.', $jobCode),
                );
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/massDisable')]

    public function massDisableAction(): void
    {
        $jobCodes = $this->getRequest()->getParam('job_codes');
        if (!is_array($jobCodes)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
            $this->_redirect('*/*/index');
            return;
        }

        try {
            /** @var Mage_Cron_Helper_Data $helper */
            $helper = Mage::helper('cron');
            $helper->setJobsEnabled($jobCodes, false);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Total of %d cron job(s) were disabled.', count($jobCodes)),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    #[Maho\Config\Route('/admin/system_tools_cronjobs/massEnable')]

    public function massEnableAction(): void
    {
        $jobCodes = $this->getRequest()->getParam('job_codes');
        if (!is_array($jobCodes)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
            $this->_redirect('*/*/index');
            return;
        }

        try {
            /** @var Mage_Cron_Helper_Data $helper */
            $helper = Mage::helper('cron');
            $helper->setJobsEnabled($jobCodes, true);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Total of %d cron job(s) were enabled.', count($jobCodes)),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }

}
