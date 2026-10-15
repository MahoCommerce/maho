<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;

class Maho_MediaCleaner_Adminhtml_MediacleanerController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/mediacleaner';

    #[Maho\Config\Route('/admin/mediacleaner/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Media Cleaner'));
        $this->loadLayout();
        $this->_setActiveMenu('system/tools/mediacleaner');
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/mediacleaner/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Start a scan that outlives the request. The client gets the token of the run at once and
     * polls scanStatus for the rest, in a MahoJobDialog.
     */
    #[Maho\Config\Route('/admin/mediacleaner/scan', methods: ['POST'])]
    public function scanAction(): void
    {
        $type = (string) $this->getRequest()->getParam('type');
        $scanner = Mage::getModel('mediacleaner/scanner');
        $types = $scanner->getTypes();
        if (!isset($types[$type])) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Unknown scan type.')]);
            return;
        }

        Maho_MediaCleaner_Model_Progress::cleanupStale();
        $progress = Mage::getModel('mediacleaner/progress');
        $progress->init([['code' => $type, 'name' => $this->__('Scan %s', $types[$type])]]);
        $this->getResponse()->sendJsonAndDetach($progress->toArray());

        $start = microtime(true);
        try {
            $progress->startStep($type);
            $count = $scanner->scan($type, fn(int $done, int $total) => $progress->reportProgress($type, $done, $total, $this->__('%d of %d folders read', $done, $total)));
            $progress->finishStepWithMessage($type, microtime(true) - $start, $this->__('%d unused files found', $count));
        } catch (Throwable $e) {
            Mage::logException($e);
            $progress->failStep($type, $e instanceof Mage_Core_Exception ? $e->getMessage() : $this->__('The scan failed'), microtime(true) - $start);
        } finally {
            $progress->finish();
            // Prevent the framework from sending a second response
            exit;
        }
    }

    #[Maho\Config\Route('/admin/mediacleaner/scanStatus')]
    public function scanStatusAction(): void
    {
        try {
            $record = Mage::getModel('mediacleaner/progress')->setToken((string) $this->getRequest()->getParam('token'))->read();
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
            return;
        }

        $this->getResponse()->setBodyJson($record === []
            ? ['error' => true, 'message' => $this->__('Unknown scan.')]
            : Maho_MediaCleaner_Model_Progress::detectInterruptedRun($record));
    }

    #[Maho\Config\Route('/admin/mediacleaner/delete')]
    public function deleteAction(): void
    {
        $image = $this->loadImage($this->getRequest()->getParam('image_id'));
        if ($image && $image->getId()) {
            $this->deleteImage($image);
        }

        $this->_redirect('*/*');
    }

    /**
     * Delete the files of the selected results in a job that outlives the request: each delete on
     * a bucket is a request, so many files take longer than any request timeout.
     */
    #[Maho\Config\Route('/admin/mediacleaner/massDelete', methods: ['POST'])]
    public function massDeleteAction(): void
    {
        $ids = array_values(array_filter((array) $this->getRequest()->getParam('ids'), is_numeric(...)));
        if ($ids === []) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $this->__('Please select the files to delete.')]);
            return;
        }

        Maho_MediaCleaner_Model_Progress::cleanupStale();
        $progress = Mage::getModel('mediacleaner/progress');
        $progress->init([['code' => 'delete', 'name' => $this->__('Delete %d files', count($ids))]]);
        $this->getResponse()->sendJsonAndDetach($progress->toArray());

        $start = microtime(true);
        $failed = 0;
        $lastReport = 0.0;
        try {
            $progress->startStep('delete');
            foreach ($ids as $i => $imageId) {
                $image = $this->loadImage($imageId);
                if ($image && $image->getId() && !$this->deleteImage($image)) {
                    $failed++;
                }
                // Write the record at most once a second, not once for each file
                if (microtime(true) - $lastReport >= 1.0 || $i + 1 === count($ids)) {
                    $lastReport = microtime(true);
                    $progress->reportProgress('delete', $i + 1, count($ids), $this->__('%d of %d files deleted', $i + 1, count($ids)));
                }
            }
            if ($failed > 0) {
                $progress->failStep('delete', $this->__('%d files could not be deleted. The log has the reason.', $failed), microtime(true) - $start);
            } else {
                $progress->finishStepWithMessage('delete', microtime(true) - $start, $this->__('%d files deleted', count($ids)));
            }
        } catch (Throwable $e) {
            Mage::logException($e);
            $progress->failStep('delete', $this->__('The delete failed'), microtime(true) - $start);
        } finally {
            $progress->finish();
            // Prevent the framework from sending a second response
            exit;
        }
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediatmp')]
    public function flushmediatmpAction(): void
    {
        $this->flushDirectory(Mage::getStorage('media'), 'tmp', 'media/tmp');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediaimport')]
    public function flushmediaimportAction(): void
    {
        $this->flushDirectory(Mage::getStorage('media'), 'import', 'media/import');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarexport')]
    public function flushvarexportAction(): void
    {
        $this->flushDirectory(Mage::getStorage('exports'), '', 'var/export');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarimportexport')]
    public function flushvarimportexportAction(): void
    {
        $dir = Mage_ImportExport_Model_Import::getWorkingDir();
        $adapter = new LocalFilesystemAdapter($dir, linkHandling: LocalFilesystemAdapter::SKIP_LINKS);
        $this->flushDirectory(new Mount('importexport', $adapter, $dir), '', 'var/importexport');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/download')]
    public function downloadAction(): void
    {
        $image = $this->loadImage($this->getRequest()->getParam('image_id'));
        if (!$image || !$image->getId()) {
            $this->_redirect('*/*');
            return;
        }

        $mount = Mage::getStorage('media');
        $file = Mage::helper('mediacleaner')->getImageStoragePath($mount, (string) $image->getType(), (string) $image->getPath());
        try {
            if ($file === null || !$mount->fileExists($file)) {
                $image->delete();
                $this->_getSession()->addError($this->__('Image not found.'));
                $this->_redirect('*/*');
                return;
            }
            $size = $mount->fileSize($file);
            $stream = $mount->readStream($file);
        } catch (FilesystemException $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('Image not found.'));
            $this->_redirect('*/*');
            return;
        }

        $this->_prepareDownloadResponse(basename($file), ['type' => 'stream', 'value' => $stream], 'application/octet-stream', $size);
    }

    #[Maho\Config\Route('/admin/mediacleaner/exportCsv')]
    public function exportCsvAction(): void
    {
        $fileName = 'unused_images.csv';
        $grid = $this->getLayout()->createBlock('mediacleaner/adminhtml_mediacleaner_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }

    #[Maho\Config\Route('/admin/mediacleaner/exportExcel')]
    public function exportExcelAction(): void
    {
        $fileName = 'unused_images.xml';
        $grid = $this->getLayout()->createBlock('mediacleaner/adminhtml_mediacleaner_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile($fileName));
    }

    #[Maho\Config\Route('/admin/mediacleaner/reset')]
    public function resetAction(): void
    {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->truncateTable($resource->getTableName('mediacleaner/image'));
        $this->_redirect('*/*');
    }

    protected function loadImage(mixed $imageId): ?Maho_MediaCleaner_Model_Image
    {
        if (!is_numeric($imageId)) {
            return null;
        }

        return Mage::getModel('mediacleaner/image')->load((int) $imageId);
    }

    protected function deleteImage(Maho_MediaCleaner_Model_Image $image): bool
    {
        $deleted = Mage::helper('mediacleaner')->deleteImageFile(
            Mage::getStorage('media'),
            (string) $image->getType(),
            (string) $image->getPath(),
        );
        if ($deleted) {
            $image->delete();
        }

        return $deleted;
    }

    protected function flushDirectory(Mount $mount, string $directory, string $label): void
    {
        if (Mage::helper('mediacleaner')->flushDirectory($mount, $directory)) {
            $this->_getSession()->addSuccess($this->__('%s was successfully flushed', $label));
        } else {
            $this->_getSession()->addError($this->__('It was not possible to delete one or more files from the %s folder.', $label));
        }
    }
}
