<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Adminhtml_Accessibilityscan_ScanController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/accessibilityscan';

    #[Maho\Config\Route('/admin/accessibilityscan_scan/view')]
    public function viewAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Scan not found'));
            $this->_redirect('*/accessibilityscan_dashboard/');
            return;
        }

        Mage::register('current_accessibilityscan_scan', $scan);

        $this->_title($this->__('System'))
            ->_title($this->__('Accessibility Scan'))
            ->_title($this->__('Scan #%s', $scan->getId()));

        $this->loadLayout();
        $this->_setActiveMenu('system/tools/accessibilityscan');
        $this->_addBreadcrumb($this->__('Accessibility Scan'), $this->__('Accessibility Scan'));
        $this->_addBreadcrumb($this->__('Scan Results'), $this->__('Scan Results'));

        $this->renderLayout();
    }

    /**
     * Create a pending scan (AJAX) and return the URLs to run and poll it.
     * Creation and execution are separate requests: a scan can outlive a
     * proxy timeout on the run request, with the status poller as the
     * source of truth for the outcome.
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
        // The store is derived from the URL: the scanner renders whatever the
        // URL serves, so a separate store selector could only contradict it
        $storeId = Mage::helper('accessibilityscan')->resolveScanUrlStoreId($url);
        if ($storeId === null) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $this->__('The URL to scan must belong to one of the configured store base URLs'),
            ]);
            return;
        }

        try {
            $helper = Mage::helper('accessibilityscan');
            $scan = Mage::getModel('accessibilityscan/scan');
            $scan->setUrl($url)
                ->setStoreId($storeId)
                ->setWcagLevel($helper->normalizeWcagLevel($this->getRequest()->getParam('wcag_level')))
                ->setTriggeredBy(Maho_AccessibilityScan_Model_Scan::TRIGGER_MANUAL)
                ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
                ->save();

            $this->getResponse()->setBodyJson([
                'success' => true,
                'scan_id' => (int) $scan->getId(),
                'run_url' => $this->getUrl('*/*/run', ['id' => $scan->getId()]),
                'status_url' => $this->getUrl('*/*/status', ['id' => $scan->getId()]),
            ]);
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Execute a pending scan (AJAX). A fronting proxy may time out long
     * runs (the first one downloads Playwright + Chromium), so the scan
     * keeps running after the client gives up and this response is only
     * advisory: the dashboard polls statusAction for the outcome.
     */
    #[Maho\Config\Route('/admin/accessibilityscan_scan/run', methods: ['POST'])]
    public function runAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId() || !$scan->isPending()) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Scan not found')]);
            return;
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            Mage::getModel('accessibilityscan/runner')->run($scan);
            $this->sendScanStatus($scan);
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Report a scan's current status (AJAX), polled while a scan runs
     */
    #[Maho\Config\Route('/admin/accessibilityscan_scan/status')]
    public function statusAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Scan not found')]);
            return;
        }
        $this->sendScanStatus($scan);
    }

    protected function sendScanStatus(Maho_AccessibilityScan_Model_Scan $scan): void
    {
        if ($scan->isFailed()) {
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => $this->__('Scan failed: %s', $scan->getData('error_message')),
            ]);
        } elseif ($scan->isComplete()) {
            $this->getResponse()->setBodyJson([
                'success' => true,
                'redirect' => $this->getUrl('*/*/view', ['id' => $scan->getId()]),
            ]);
        } else {
            $this->getResponse()->setBodyJson([
                'success' => true,
                'status' => (string) $scan->getStatus(),
            ]);
        }
    }

    #[Maho\Config\Route('/admin/accessibilityscan_scan/delete', methods: ['POST'])]
    public function deleteAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        if (!$scan->getId()) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Scan not found'));
            $this->_redirect('*/accessibilityscan_dashboard/');
            return;
        }

        try {
            $scan->deleteWithScreenshots();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The scan has been deleted.'));
        } catch (Throwable $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/accessibilityscan_dashboard/');
    }

    /**
     * Serve a page screenshot of the scan inline (it lives in var/, outside
     * the docroot). The page_id parameter selects the viewport's page;
     * without it the scan's first page is served.
     */
    #[Maho\Config\Route('/admin/accessibilityscan_scan/screenshot')]
    public function screenshotAction(): void
    {
        $scan = Mage::getModel('accessibilityscan/scan')->load((int) $this->getRequest()->getParam('id'));
        $page = $scan->getId() ? $scan->getFirstPage() : null;
        if ($scan->getId() && ($pageId = (int) $this->getRequest()->getParam('page_id'))) {
            $page = null;
            foreach ($scan->getPages() as $scanPage) {
                if ((int) $scanPage->getId() === $pageId) {
                    $page = $scanPage;
                    break;
                }
            }
        }
        $file = $page?->getScreenshotFile();
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
