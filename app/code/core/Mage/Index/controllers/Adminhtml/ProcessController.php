<?php

/**
 * Maho
 *
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Index_Adminhtml_ProcessController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/index';

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
    public function reindexProcessAction(): void
    {
        /** @var Mage_Index_Model_Process $process */
        $process = $this->_initProcess();
        if ($process) {
            try {
                \Maho\Profiler::start('__INDEX_PROCESS_REINDEX_ALL__');

                $process->reindexEverything();
                \Maho\Profiler::stop('__INDEX_PROCESS_REINDEX_ALL__');
                $this->_getSession()->addSuccess(
                    Mage::helper('index')->__('%s index was rebuilt.', $process->getIndexer()->getName()),
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException(
                    $e,
                    Mage::helper('index')->__('There was a problem with reindexing process.'),
                );
            }
        } else {
            $this->_getSession()->addError(
                Mage::helper('index')->__('Cannot initialize the indexer process.'),
            );
        }

        $this->_redirect('*/*/list');
    }

    /**
     * Reindex pending events for index process
     */
    public function reindexEventsAction(): void {}

    /**
     * Rebiuld all processes index
     */
    public function reindexAllAction(): void {}

    /**
     * Mass rebuild selected processes index
     */
    public function massReindexAction(): void
    {
        /** @var Mage_Index_Model_Indexer $indexer */
        $indexer    = Mage::getSingleton('index/indexer');
        $processIds = $this->getRequest()->getParam('process');
        if (empty($processIds) || !is_array($processIds)) {
            $this->_getSession()->addError(Mage::helper('index')->__('Please select Indexes'));
        } else {
            try {
                $counter = 0;
                foreach ($processIds as $processId) {
                    /** @var Mage_Index_Model_Process $process */
                    $process = $indexer->getProcessById($processId);
                    if ($process && $process->getIndexer()->isVisible()) {
                        $process->reindexEverything();
                        $counter++;
                    }
                }
                $this->_getSession()->addSuccess(
                    Mage::helper('index')->__('Total of %d index(es) have reindexed data.', $counter),
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException($e, Mage::helper('index')->__('Cannot initialize the indexer process.'));
            }
        }

        $this->_redirect('*/*/list');
    }

    /**
     * Mass change index mode of selected processes index
     */
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

        $this->_redirect('*/*/list');
    }
}
