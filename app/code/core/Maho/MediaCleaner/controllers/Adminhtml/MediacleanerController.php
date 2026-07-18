<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Adminhtml_MediacleanerController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/mediacleaner';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions([
            'synccategory',
            'syncproduct',
            'syncproductcache',
            'syncwysiwyg',
            'delete',
            'massDelete',
            'flushmediatmp',
            'flushmediaimport',
            'flushvarexport',
            'flushvarimportexport',
            'reset',
        ]);
        return parent::preDispatch();
    }

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
        $entityTypeId = Mage::getModel('catalog/category')->getResource()->getTypeId();
        $mediaDir = Mage::getBaseDir('media') . '/catalog/category';
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        if (!is_dir($mediaDir)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('"media/catalog/category" folder does not exist.'));
            $this->_redirect('*/*');
            return;
        }

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

        $fsImages = Mage::helper('mediacleaner')->scandirRecursive($mediaDir);
        $fsImages = str_replace("{$mediaDir}/", '', $fsImages);

        $this->storeUnusedImages('category', array_diff($fsImages, $dbImages));

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncproduct')]
    public function syncproductAction(): void
    {
        $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $mediaDir = Mage::getBaseDir('media') . '/catalog/product';
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');

        if (!is_dir($mediaDir)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('"media/catalog/product" folder does not exist.'));
            $this->_redirect('*/*');
            return;
        }

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
            $dbImages = array_map([$this, 'removeLeadingSlash'], $dbImages);
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
        $mediaGallery = array_map([$this, 'removeLeadingSlash'], $mediaGallery);

        $fsImages = Mage::helper('mediacleaner')->scandirRecursive($mediaDir);
        $fsImages = str_replace("{$mediaDir}/", '', $fsImages);

        $this->storeUnusedImages('product', array_diff($fsImages, $dbImages, $mediaGallery));

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncproductcache')]
    public function syncproductcacheAction(): void
    {
        $mediaDir = Mage::getBaseDir('media') . '/catalog/product/cache';
        $mediaDirNoCache = Mage::getBaseDir('media') . '/catalog/product';

        if (!is_dir($mediaDir)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('"media/catalog/product/cache" folder does not exist.'));
            $this->_redirect('*/*');
            return;
        }

        $fsImages = Mage::helper('mediacleaner')->scandirRecursive($mediaDir);
        $fsImages = str_replace("{$mediaDir}/", '', $fsImages);

        $unusedImages = [];
        foreach ($fsImages as $fsImage) {
            if (str_contains($fsImage, '/placeholder/')) {
                continue;
            }

            // The trailing segments mirror the source image's dispersed path (e.g. m/y/file).
            // Maho rewrites the cached extension to the configured output format (webp by default),
            // so match the original ignoring extension rather than comparing the cached filename verbatim.
            $pathNoCache = implode('/', array_slice(explode('/', $fsImage), -3));
            $base = preg_replace('/\.[^.\/]+$/', '', $pathNoCache);
            if (!glob("{$mediaDirNoCache}/{$base}.*", GLOB_NOSORT)) {
                $unusedImages[] = $fsImage;
            }
        }

        $this->storeUnusedImages('product_cache', $unusedImages);

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/syncwysiwyg')]
    public function syncwysiwygAction(): void
    {
        $mediaDir = Mage::getBaseDir('media') . '/wysiwyg';
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $helper = Mage::helper('mediacleaner');

        if (!is_dir($mediaDir)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('"media/wysiwyg" folder does not exist.'));
            $this->_redirect('*/*');
            return;
        }

        $dbImages = array_merge(
            $db->fetchCol($db->select()->from($resource->getTableName('cms/page'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('cms/block'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_text')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_styles')),
        );

        $cssFiles = $helper->getAllCSSFilesContents();
        $fsImages = $helper->scandirRecursive($mediaDir);
        $fsImages = str_replace(Mage::getBaseDir('media') . '/', '', $fsImages);
        $swatchesEnabled = Mage::getStoreConfigFlag('configswatches/general/enabled');

        $usedImages = [];
        foreach ($fsImages as $fsImage) {
            if ($swatchesEnabled && fnmatch('wysiwyg/swatches/*', $fsImage)) {
                $usedImages[] = $fsImage;
            }
            foreach ($dbImages as $dbImage) {
                if (stripos($dbImage ?? '', $fsImage) !== false) {
                    $usedImages[] = $fsImage;
                    break;
                }
            }
            foreach ($cssFiles as $cssFile) {
                if (stripos($cssFile, $fsImage) !== false) {
                    $usedImages[] = $fsImage;
                    break;
                }
            }
        }

        $unusedImages = array_diff($fsImages, $usedImages);
        $unusedImages = str_replace('wysiwyg/', '', $unusedImages);

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
            Mage::getSingleton('adminhtml/session')->addError($this->__('It was not possible to delete one or more files from the filesystem.'));
        }

        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediatmp')]
    public function flushmediatmpAction(): void
    {
        $this->flushDirectory(Mage::getBaseDir('media') . '/tmp', 'media/tmp');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushmediaimport')]
    public function flushmediaimportAction(): void
    {
        $this->flushDirectory(Mage::getBaseDir('media') . '/import', 'media/import');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarexport')]
    public function flushvarexportAction(): void
    {
        $this->flushDirectory(Mage::getBaseDir('var') . '/export', 'var/export');
        $this->_redirect('*/*');
    }

    #[Maho\Config\Route('/admin/mediacleaner/flushvarimportexport')]
    public function flushvarimportexportAction(): void
    {
        $this->flushDirectory(Mage::getBaseDir('var') . '/importexport', 'var/importexport');
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

        $imagePath = Mage::helper('mediacleaner')->getMediaDirByType($image->getType()) . $image->getPath();
        if (!file_exists($imagePath)) {
            $image->delete();
            Mage::getSingleton('adminhtml/session')->addError($this->__('Image not found.'));
            $this->_redirect('*/*');
            return;
        }

        $this->_prepareDownloadResponse(basename($imagePath), file_get_contents($imagePath));
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
        $imagePath = Mage::helper('mediacleaner')->getMediaDirByType($image->getType()) . $image->getPath();
        if (!file_exists($imagePath)) {
            $image->delete();
            return true;
        }

        if (unlink($imagePath)) {
            $image->delete();
            return true;
        }

        return false;
    }

    protected function flushDirectory(string $dir, string $label): void
    {
        \Maho\Io\File::rmdirRecursive($dir, true);
        @mkdir($dir);

        $leftoverFiles = Mage::helper('mediacleaner')->scandirRecursive($dir);
        if ($leftoverFiles) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('It was not possible to delete one or more files from the %s folder.', $label));
        } else {
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%s was successfully flushed', $label));
        }
    }

    protected function removeLeadingSlash(string $imagePath): string
    {
        return ltrim($imagePath, '/');
    }
}
