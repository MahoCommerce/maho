<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Adminhtml_Accessibilityscan_ScanController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/accessibilityscan/scans';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['start', 'delete']);
        return parent::preDispatch();
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Accessibility Scan'))
            ->_title($this->__('Scan History'));

        $this->loadLayout();
        $this->_setActiveMenu('system/accessibilityscan/scans');
        $this->_addBreadcrumb($this->__('Accessibility Scan'), $this->__('Accessibility Scan'));
        $this->_addBreadcrumb($this->__('Scan History'), $this->__('Scan History'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/view')]
    public function viewAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Scan not found'));
            $this->_redirect('*/*/');
            return;
        }

        Mage::register('current_accessibilityscan_scan', $scan);

        $this->_title($this->__('System'))
            ->_title($this->__('Accessibility Scan'))
            ->_title($this->__('Scan #%s', $scan->getId()));

        $this->loadLayout();
        $this->_setActiveMenu('system/accessibilityscan/scans');
        $this->_addBreadcrumb($this->__('Accessibility Scan'), $this->__('Accessibility Scan'));
        $this->_addBreadcrumb($this->__('Scan Results'), $this->__('Scan Results'));

        $this->renderLayout();
    }

    /**
     * Start a scan (AJAX). Runs synchronously and returns the result URL.
     */
    #[Maho\Config\Route('/admin/accessibilityscan_scan/start', methods: ['POST'])]
    public function startAction(): void
    {
        $url = (string) $this->getRequest()->getParam('url');
        if ($url === '' || !Mage::helper('core')->isValidUrl($url)) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $this->__('Please provide a valid URL to scan'),
            ]);
            return;
        }
        if (!Mage::helper('accessibilityscan')->isAllowedScanUrl($url)) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $this->__('The URL to scan must belong to one of the configured store base URLs'),
            ]);
            return;
        }

        // First run downloads Playwright + Chromium and can take several minutes
        @set_time_limit(0);

        try {
            $helper = Mage::helper('accessibilityscan');
            $scan = Mage::getModel('accessibilityscan/scan');
            $scan->setUrl($url)
                ->setStoreId((int) $this->getRequest()->getParam('store_id'))
                ->setWcagLevel($helper->normalizeWcagLevel($this->getRequest()->getParam('wcag_level')))
                ->setTriggeredBy(Maho_AccessibilityScan_Model_Scan::TRIGGER_MANUAL)
                ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
                ->save();

            Mage::getModel('accessibilityscan/runner')->run($scan);

            if ($scan->isFailed()) {
                $this->getResponse()->setBodyJson([
                    'error' => true,
                    'message' => $this->__('Scan failed: %s', $scan->getData('error_message')),
                ]);
                return;
            }

            $this->getResponse()->setBodyJson([
                'success' => true,
                'scan_id' => (int) $scan->getId(),
                'redirect' => $this->getUrl('*/*/view', ['id' => $scan->getId()]),
            ]);
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/delete', methods: ['POST'])]
    public function deleteAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Scan not found'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $scan->deleteWithScreenshots();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The scan has been deleted.'));
        } catch (Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * Serve the scan's page screenshot inline (it lives in var/, outside the docroot)
     */
    #[Maho\Config\Route('/admin/accessibilityscan_scan/screenshot')]
    public function screenshotAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        $file = $scan->getId() ? $scan->getFirstPage()?->getScreenshotFile() : null;
        if ($file === null) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'image/png', true)
            ->setHeader('Content-Length', (string) filesize($file), true)
            ->setBody((string) file_get_contents($file));
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/exportPdf')]
    public function exportPdfAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Scan not found'));
            $this->_redirect('*/*/');
            return;
        }

        /** @var Maho_AccessibilityScan_Block_Pdf_Report $block */
        $block = $this->getLayout()->createBlock('accessibilityscan/pdf_report');
        $block->setScan($scan);

        $this->_prepareDownloadResponse(
            'accessibility-scan-' . $scan->getId() . '.pdf',
            $block->renderPdf(),
            'application/pdf',
        );
    }
}
