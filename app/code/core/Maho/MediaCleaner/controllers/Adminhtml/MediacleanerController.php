<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;

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

    #[Maho\Config\Route('/admin/mediacleaner/synccategory')]
    public function synccategoryAction(): void
    {
        $fsImages = $this->listMediaFiles('catalog/category', $this->__('"media/catalog/category" folder does not exist.'));
        if ($fsImages === null) {
            $this->_redirect('*/*');
            return;
        }

        $entityTypeId = Mage::getModel('catalog/category')->getResource()->getTypeId();
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $attributeIds = $db->fetchCol(
            $db->select()
                ->from($resource->getTableName('eav/attribute'), 'attribute_id')
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('frontend_input = ?', 'image'),
        );

        $dbImages = [];
        if ($attributeIds) {
            $dbImages = $db->fetchCol(
                $db->select()
                    ->from($resource->getTableName('catalog_category_entity_varchar'), 'value')
                    ->where('value IS NOT NULL')
                    ->where('LENGTH(value) > 0')
                    ->where('entity_type_id = ?', $entityTypeId)
                    ->where('attribute_id IN (?)', $attributeIds),
            );
        }

        $this->storeUnusedImages('category', array_diff($fsImages, $dbImages));

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncproduct')]
    public function syncproductAction(): void
    {
        $fsImages = $this->listMediaFiles('catalog/product', $this->__('"media/catalog/product" folder does not exist.'));
        if ($fsImages === null) {
            $this->_redirect('*/*');
            return;
        }

        $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        $attributeIds = $db->fetchCol(
            $db->select()
                ->from($resource->getTableName('eav/attribute'), 'attribute_id')
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('frontend_input = ?', 'media_image'),
        );

        $dbImages = [];
        if ($attributeIds) {
            $dbImages = $db->fetchCol(
                $db->select()
                    ->from($resource->getTableName('catalog_product_entity_varchar'), 'value')
                    ->where('value IS NOT NULL')
                    ->where('LENGTH(value) > 0')
                    ->where('entity_type_id = ?', $entityTypeId)
                    ->where('attribute_id IN (?)', $attributeIds)
                    ->where('value <> ?', 'no_selection'),
            );
            $dbImages = array_map($this->removeLeadingSlash(...), $dbImages);
        }

        $placeholders = $db->fetchCol(
            $db->select()
                ->distinct()
                ->from($resource->getTableName('core/config_data'), 'value')
                ->where('path LIKE ?', 'catalog/placeholder/%_placeholder'),
        );
        foreach ($placeholders as $placeholder) {
            $dbImages[] = "placeholder/{$placeholder}";
        }

        $mediaGallery = $db->fetchCol(
            $db->select()
                ->from($resource->getTableName('catalog/product_attribute_media_gallery'), 'value')
                ->where('value IS NOT NULL')
                ->where('LENGTH(value) > 0'),
        );
        $mediaGallery = array_map($this->removeLeadingSlash(...), $mediaGallery);

        $this->storeUnusedImages('product', array_diff($fsImages, $dbImages, $mediaGallery));

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncproductcache')]
    public function syncproductcacheAction(): void
    {
        try {
            if ($this->isMissingDirectory('catalog/product/cache')) {
                $this->_getSession()->addError($this->__('"media/catalog/product/cache" folder does not exist.'));
                $this->_redirect('*/*');
                return;
            }
            $unusedImages = Mage::helper('mediacleaner')
                ->findUnusedProductCacheFiles(Mage::getStorage('media'), Maho::getConfiguredImageExtension());
        } catch (FilesystemException $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('It was not possible to read the "%s" folder.', 'media/catalog/product'));
            $this->_redirect('*/*');
            return;
        }

        $this->storeUnusedImages('product_cache', $unusedImages);

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncwysiwyg')]
    public function syncwysiwygAction(): void
    {
        $fsImages = $this->listMediaFiles('wysiwyg', $this->__('"media/wysiwyg" folder does not exist.'));
        if ($fsImages === null) {
            $this->_redirect('*/*');
            return;
        }

        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $helper = Mage::helper('mediacleaner');

        $contents = array_merge(
            $db->fetchCol($db->select()->from($resource->getTableName('cms/page'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('cms/block'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_text')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_styles')),
            $helper->getAllCSSFilesContents(),
        );

        $unusedImages = $helper->getUnusedWysiwygFiles(
            $fsImages,
            array_map(strval(...), $contents),
            Mage::getStoreConfigFlag('configswatches/general/enabled'),
        );

        $this->storeUnusedImages('wysiwyg', $unusedImages);

        $this->_redirect('*/*');
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

    #[Maho\Config\Route('/admin/mediacleaner/massDelete')]
    public function massDeleteAction(): void
    {
        $ids = $this->getRequest()->getParam('ids');
        $errorMessageThrown = false;
        if (is_array($ids)) {
            foreach ($ids as $imageId) {
                $image = $this->loadImage($imageId);
                if ($image && $image->getId() && !$this->deleteImage($image)) {
                    $errorMessageThrown = true;
                }
            }
        }

        if ($errorMessageThrown) {
            $this->_getSession()->addError($this->__('It was not possible to delete one or more files from the filesystem.'));
        }

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediatmp')]
    public function flushmediatmpAction(): void
    {
        $this->flushDirectory('media', 'tmp', 'media/tmp');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediaimport')]
    public function flushmediaimportAction(): void
    {
        $this->flushDirectory('media', 'import', 'media/import');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarexport')]
    public function flushvarexportAction(): void
    {
        $this->flushDirectory('exports', '', 'var/export');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarimportexport')]
    public function flushvarimportexportAction(): void
    {
        $this->flushDirectory('importexport', '', 'var/importexport');
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
        $file = Mage::helper('mediacleaner')->getImageMountPath($mount, (string) $image->getType(), (string) $image->getPath());
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

    protected function storeUnusedImages(string $type, array $unusedImages): void
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $table = $resource->getTableName('mediacleaner/image');

        // Replace this type's previous results so files that are no longer orphan
        // drop off the list (otherwise a re-scan could leave a now-referenced file
        // flagged for deletion).
        $db->delete($table, $db->quoteInto('type = ?', $type));

        foreach ($unusedImages as $path) {
            $db->insertIgnore($table, ['type' => $type, 'path' => $path]);
        }
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

    protected function flushDirectory(string $mountName, string $directory, string $label): void
    {
        if (Mage::helper('mediacleaner')->flushDirectory(Mage::getStorage($mountName), $directory)) {
            $this->_getSession()->addSuccess($this->__('%s was successfully flushed', $label));
        } else {
            $this->_getSession()->addError($this->__('It was not possible to delete one or more files from the %s folder.', $label));
        }
    }

    /**
     * The files that a scan examines below $directory on the media mount, or null after an error message.
     *
     * @return list<string>|null
     */
    protected function listMediaFiles(string $directory, string $missingMessage): ?array
    {
        try {
            if ($this->isMissingDirectory($directory)) {
                $this->_getSession()->addError($missingMessage);
                return null;
            }

            return Mage::helper('mediacleaner')->listFiles(Mage::getStorage('media'), $directory);
        } catch (FilesystemException $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('It was not possible to read the "%s" folder.', 'media/' . $directory));
            return null;
        }
    }

    /**
     * Only a local disk has real directories. On a bucket, a directory without files is an empty listing.
     *
     * @throws FilesystemException
     */
    protected function isMissingDirectory(string $directory): bool
    {
        $mount = Mage::getStorage('media');

        return $mount->isLocal() && !$mount->directoryExists($directory);
    }

    protected function removeLeadingSlash(string $imagePath): string
    {
        return ltrim($imagePath, '/');
    }
}
