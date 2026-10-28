<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Adminhtml_ExportController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/convert/export';

    /**
     * The download link in the notification has no secret key. The download only reads a file
     * with a random name, and the ACL check above still applies.
     */
    #[\Override]
    protected $_publicActions = ['download'];

    /**
     * Custom constructor.
     */
    #[\Override]
    protected function _construct()
    {
        // Define module dependent translate
        $this->setUsedModuleName('Mage_ImportExport');
    }

    /**
     * Initialize layout.
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this
            ->_title($this->__('Import/Export'))
            ->loadLayout()
            ->_setActiveMenu('system/convert/export');

        return $this;
    }

    /**
     * Queue the export with the filters of the form. The worker writes the file and sends a link
     * to it in a notification.
     */
    #[Maho\Config\Route('/admin/export/export')]
    public function exportAction(): void
    {
        if ($this->getRequest()->getPost(Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP)) {
            try {
                /** @var Mage_ImportExport_Model_Export $model */
                $model = Mage::getModel('importexport/export');
                $model->setData($this->getRequest()->getParams())
                    ->queueExport(Mage::helper('adminnotification')->getCurrentAdminUserId());
                $this->_getSession()->addSuccess(
                    $this->__('The export will run in the background. A message in the notifications tells you when the file is ready.'),
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('No valid data sent'));
            }
        } else {
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Download an export file that a queued export wrote.
     */
    #[Maho\Config\Route('/admin/export/download')]
    public function downloadAction(): void
    {
        /** @var Mage_ImportExport_Model_Export $model */
        $model = Mage::getModel('importexport/export');
        $path = $model->getStoragePath((string) $this->getRequest()->getParam('file'));
        $mount = $model->getStorage();
        if ($path === null || !$mount->fileExists($path)) {
            $this->_getSession()->addError(
                $this->__('The export file does not exist. Each export deletes the export files that are older than %d days.', Mage_ImportExport_Model_Export::FILE_LIFETIME_DAYS),
            );
            $this->_redirect('*/*/index');
            return;
        }

        $this->_prepareDownloadResponse(
            basename($path),
            ['type' => 'stream', 'value' => $mount->readStream($path)],
            'application/octet-stream',
            $mount->fileSize($path),
        );
    }

    /**
     * Index action.
     */
    #[Maho\Config\Route('/admin/export/index')]
    public function indexAction(): void
    {
        $this->_initAction()
            ->_title($this->__('Export'))
            ->_addBreadcrumb($this->__('Export'), $this->__('Export'));

        $this->renderLayout();
    }

    /**
     * Get grid-filter of entity attributes action.
     */
    #[Maho\Config\Route('/admin/export/getFilter')]
    public function getFilterAction()
    {
        $data = $this->getRequest()->getParams();
        if ($data) {
            try {
                $this->loadLayout();

                /** @var Mage_ImportExport_Block_Adminhtml_Export_Filter $attrFilterBlock */
                $attrFilterBlock = $this->getLayout()->getBlock('export.filter');
                /** @var Mage_ImportExport_Model_Export $export */
                $export = Mage::getModel('importexport/export');

                $export->filterAttributeCollection(
                    $attrFilterBlock->prepareCollection(
                        $export->setData($data)->getEntityAttributeCollection(),
                    ),
                );
                return $this->renderLayout();
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        } else {
            $this->_getSession()->addError($this->__('No valid data sent'));
        }
        $this->_redirect('*/*/index');
    }
}
