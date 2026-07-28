<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

use Maho\Job\StepState;

class Mage_Index_Adminhtml_ProcessController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/index';

    #[\Override]
    public function preDispatch(): self
    {
        $this->_setForcedFormKeyActions(['reindexProcess', 'reindexAll', 'massReindex', 'massChangeMode']);
        return parent::preDispatch();
    }

    /**
     * Initialize process object by request
     *
     * @return Mage_Index_Model_Process|false
     */
    protected function _initProcess()
    {
        $processId = $this->getRequest()->getParam('process');
        if ($processId) {
            /** @var Mage_Index_Model_Process $process */
            $process = Mage::getModel('index/process')->load($processId);
            if ($process->getId() && $process->getIndexer()->isVisible()) {
                return $process;
            }
        }
        return false;
    }

    /**
     * Display processes grid action
     */
    #[Maho\Config\Route('/admin/process/list')]
    public function listAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Index Management'));

        $this->loadLayout();
        $this->_setActiveMenu('system/index');
        $this->renderLayout();
    }

    /**
     * Process detail and edit action
     */
    #[Maho\Config\Route('/admin/process/edit')]
    public function editAction(): void
    {
        /** @var Mage_Index_Model_Process $process */
        $process = $this->_initProcess();
        if ($process) {
            $this
                ->_title($process->getIndexCode())
                ->_title($this->__('System'))
                ->_title($this->__('Index Management'))
                ->_title($this->__($process->getIndexer()->getName()));

            Mage::register('current_index_process', $process);
            $this
                ->loadLayout()
                ->_setActiveMenu('system/index')
                ->renderLayout();
        } else {
            $this->_getSession()->addError(
                Mage::helper('index')->__('Cannot initialize the indexer process.'),
            );
            $this->_redirect('*/*/list');
        }
    }

    /**
     * Save process data
     */
    #[Maho\Config\Route('/admin/process/save')]
    public function saveAction(): void
    {
        /** @var Mage_Index_Model_Process $process */
        $process = $this->_initProcess();
        if ($process) {
            $mode = $this->getRequest()->getPost('mode');
            if ($mode) {
                $process->setMode($mode);
            }
            try {
                $process->save();
                $this->_getSession()->addSuccess(
                    Mage::helper('index')->__('The index has been saved.'),
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException(
                    $e,
                    Mage::helper('index')->__('There was a problem with saving process.'),
                );
            }
            $this->_redirect('*/*/list');
        } else {
            $this->_getSession()->addError(
                Mage::helper('index')->__('Cannot initialize the indexer process.'),
            );
            $this->_redirect('*/*/list');
        }
    }

    /**
     * Reindex all data what process is responsible
     */
    #[Maho\Config\Route('/admin/process/reindexProcess')]
    public function reindexProcessAction(): void
    {
        /** @var Mage_Index_Model_Process $process */
        $process = $this->_initProcess();
        if (!$process) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => Mage::helper('index')->__('Cannot initialize the indexer process.'),
            ]);
            return;
        }

        $this->_startReindex([$process->getId()]);
    }

    /**
     * Reindex pending events for index process
     */
    #[Maho\Config\Route('/admin/process/reindexEvents')]
    public function reindexEventsAction(): void {}

    /**
     * Rebuild all processes index
     */
    #[Maho\Config\Route('/admin/process/reindexAll')]
    public function reindexAllAction(): void
    {
        $this->_startReindex(null);
    }

    /**
     * Mass rebuild selected processes index
     */
    #[Maho\Config\Route('/admin/process/massReindex')]
    public function massReindexAction(): void
    {
        $processIds = $this->getRequest()->getParam('process');
        if (empty($processIds) || !is_array($processIds)) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => Mage::helper('index')->__('Please select Indexes'),
            ]);
            return;
        }

        $this->_startReindex($processIds);
    }

    /**
     * Report the state of a backgrounded reindex run
     */
    #[Maho\Config\Route('/admin/process/reindexStatus')]
    public function reindexStatusAction(): void
    {
        try {
            /** @var Mage_Index_Model_Progress $progress */
            $progress = Mage::getModel('index/progress');
            $record = $progress->setToken((string) $this->getRequest()->getParam('token'))->read();
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
            return;
        }

        if (!$record) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => Mage::helper('index')->__('Unknown reindex run.'),
            ]);
            return;
        }

        $this->getResponse()->setBodyJson($this->_detectInterruptedRun($record));
    }

    /**
     * Kick off a reindex that outlives the request.
     *
     * The client gets the run token straight away and polls reindexStatus for the rest; a null
     * $processIds means every visible index.
     */
    protected function _startReindex(?array $processIds): void
    {
        /** @var Mage_Index_Model_Runner $runner */
        $runner = Mage::getModel('index/runner');
        $queue = $runner->buildQueue($processIds);

        if (!$queue) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => Mage::helper('index')->__('Cannot initialize the indexer process.'),
            ]);
            return;
        }

        Mage_Index_Model_Progress::cleanupStale();

        /** @var Mage_Index_Model_Progress $progress */
        $progress = Mage::getModel('index/progress');
        $progress->init($runner->getSteps($queue));

        $this->getResponse()->sendJsonAndDetach($progress->toArray());

        try {
            $runner->run($queue, $progress);
        } catch (Throwable $e) {
            Mage::logException($e);
        } finally {
            // Prevent the framework from sending a second response
            exit;
        }
    }

    /**
     * A worker killed mid-reindex never writes to its record again. Left alone the client would
     * poll a "running" run forever, so report it as interrupted once nothing holds the runner's
     * lock for the step, which only a live worker can be holding.
     */
    protected function _detectInterruptedRun(array $record): array
    {
        if (!empty($record['finished'])
            || time() - (int) ($record['updated_at'] ?? 0) < Mage_Index_Model_Progress::STALE_AFTER
        ) {
            return $record;
        }

        /** @var Mage_Core_Model_Lock $lock */
        $lock = Mage::getSingleton('core/lock');

        $steps = $record['steps'] ?? [];
        foreach ($steps as &$step) {
            if ($step['state'] === StepState::Running->value) {
                if ($lock->isHeld(Mage_Index_Model_Runner::lockName((string) $record['token'], $step['code']))) {
                    // Still working, it just has nothing to report mid-index
                    return $record;
                }
                $step['state'] = StepState::Error->value;
                $step['message'] = Mage::helper('index')->__('The reindex process was interrupted.');
            } elseif ($step['state'] === StepState::Queued->value) {
                $step['state'] = StepState::Skipped->value;
                $step['message'] = Mage::helper('index')->__('Not started.');
            }
        }
        unset($step);

        $record['steps'] = $steps;
        $record['finished'] = true;
        return $record;
    }

    /**
     * Mass change index mode of selected processes index
     */
    #[Maho\Config\Route('/admin/process/massChangeMode')]
    public function massChangeModeAction(): void
    {
        $processIds = $this->getRequest()->getParam('process');
        if (empty($processIds) || !is_array($processIds)) {
            $this->_getSession()->addError(Mage::helper('index')->__('Please select Index(es)'));
        } else {
            try {
                $counter = 0;
                $mode = $this->getRequest()->getParam('index_mode');
                foreach ($processIds as $processId) {
                    /** @var Mage_Index_Model_Process $process */
                    $process = Mage::getModel('index/process')->load($processId);
                    if ($process->getId() && $process->getIndexer()->isVisible()) {
                        $process->setMode($mode)->save();
                        $counter++;
                    }
                }
                $this->_getSession()->addSuccess(
                    Mage::helper('index')->__('Total of %d index(es) have changed index mode.', $counter),
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException($e, Mage::helper('index')->__('Cannot initialize the indexer process.'));
            }
        }

        // The grid posts mass-actions over ajax, so the messages are picked up on the reload
        $this->getResponse()->setBodyJson(['success' => true]);
    }
}
