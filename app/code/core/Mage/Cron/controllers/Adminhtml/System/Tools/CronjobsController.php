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
        $this->_setForcedFormKeyActions(['massDelete', 'massDisable', 'massEnable', 'run', 'toggle']);
        return parent::preDispatch();
    }

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
            Mage::helper('cron')->__('Cron Schedule'),
            Mage::helper('cron')->__('Cron Schedule'),
        );
        $this->_addContent($this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs'));
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_grid')->toHtml(),
        );
    }

    public function massDeleteAction(): void
    {
        $scheduleIds = $this->getRequest()->getParam('schedule_ids');
        if (!is_array($scheduleIds)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
        } else {
            try {
                $collection = Mage::getModel('cron/schedule')->getCollection()
                    ->addFieldToFilter('schedule_id', ['in' => $scheduleIds]);
                $deletedCount = count($collection);
                foreach ($collection as $schedule) {
                    $schedule->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Total of %d cron job(s) were deleted.', $deletedCount),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function configuredAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('system/tools/cronjobs_configured');
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('System'),
            Mage::helper('cron')->__('System'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Tools'),
            Mage::helper('cron')->__('Tools'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Configured Cron Jobs'),
            Mage::helper('cron')->__('Configured Cron Jobs'),
        );
        $this->_addContent($this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_configured'));
        $this->renderLayout();
    }

    public function configuredGridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_configured_grid')->toHtml(),
        );
    }

    public function runAction(): void
    {
        $jobCode = $this->getRequest()->getParam('job_code');
        if (!$jobCode) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('No job code specified.')]);
            return;
        }

        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');
        $jobs = $helper->getConfiguredJobs();
        if (!isset($jobs[$jobCode])) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Unknown cron job: %s', $jobCode)]);
            return;
        }

        $modelMethod = $jobs[$jobCode]['model_method'];
        if (!preg_match(Mage_Cron_Model_Observer::REGEX_RUN_MODEL, $modelMethod, $run)) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Invalid model/method definition, expecting "model/class::method".')]);
            return;
        }

        $model = Mage::getModel($run[1]);
        if (!$model || !method_exists($model, $run[2])) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Invalid callback: %s::%s does not exist', $run[1], $run[2])]);
            return;
        }

        $schedule = Mage::getModel('cron/schedule');
        $now = date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT);
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
                ->setFinishedAt(date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT))
                ->save();
        } catch (Exception $e) {
            $schedule->setStatus(Mage_Cron_Model_Schedule::STATUS_ERROR)
                ->setMessages($e->getMessage())
                ->setFinishedAt(date(\Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT))
                ->save();
        }

        // Prevent framework from sending another response
        exit;
    }

    public function runStatusAction(): void
    {
        $scheduleId = (int) $this->getRequest()->getParam('schedule_id');
        if (!$scheduleId) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('No schedule ID specified.')]);
            return;
        }

        $schedule = Mage::getModel('cron/schedule')->load($scheduleId);
        if (!$schedule->getId()) {
            $this->_sendJsonResponse(['error' => true, 'message' => $this->__('Schedule not found.')]);
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

        $this->_sendJsonResponse($data);
    }

    public function toggleAction(): void
    {
        $jobCode = $this->getRequest()->getParam('job_code');
        if (!$jobCode) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('No job code specified.'));
            $this->_redirect('*/*/configured');
            return;
        }

        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');
        $isCurrentlyDisabled = $helper->isJobDisabled($jobCode);

        try {
            $helper->setJobDisabled($jobCode, !$isCurrentlyDisabled);
            if ($isCurrentlyDisabled) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Cron job "%s" has been enabled.', $jobCode),
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Cron job "%s" has been disabled.', $jobCode),
                );
            }
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/configured');
    }

    public function massDisableAction(): void
    {
        $jobCodes = $this->getRequest()->getParam('job_codes');
        if (!is_array($jobCodes)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
            $this->_redirect('*/*/configured');
            return;
        }

        try {
            /** @var Mage_Cron_Helper_Data $helper */
            $helper = Mage::helper('cron');
            $count = 0;
            foreach ($jobCodes as $jobCode) {
                if (!$helper->isJobDisabled($jobCode)) {
                    $helper->setJobDisabled($jobCode, true);
                    $count++;
                }
            }
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Total of %d cron job(s) were disabled.', $count),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/configured');
    }

    public function massEnableAction(): void
    {
        $jobCodes = $this->getRequest()->getParam('job_codes');
        if (!is_array($jobCodes)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select cron job(s).'));
            $this->_redirect('*/*/configured');
            return;
        }

        try {
            /** @var Mage_Cron_Helper_Data $helper */
            $helper = Mage::helper('cron');
            $count = 0;
            foreach ($jobCodes as $jobCode) {
                if ($helper->isJobDisabled($jobCode)) {
                    $helper->setJobDisabled($jobCode, false);
                    $count++;
                }
            }
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Total of %d cron job(s) were enabled.', $count),
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/configured');
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }

    protected function _sendJsonResponse(array $data): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json', true)
            ->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
