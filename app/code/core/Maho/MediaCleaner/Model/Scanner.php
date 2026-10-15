<?php

/**
 * Finds the files on the media mount that nothing uses, and stores them as scan results.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;

class Maho_MediaCleaner_Model_Scanner
{
    /**
     * The scan types and their labels.
     *
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        $helper = Mage::helper('mediacleaner');
        return [
            'product'       => $helper->__('Products'),
            'category'      => $helper->__('Categories'),
            'wysiwyg'       => $helper->__('WYSIWYG'),
            'product_cache' => $helper->__('Product Cache'),
        ];
    }

    /**
     * Replace the results of $type with the files that nothing uses.
     *
     * @param (callable(int $done, int $total): void)|null $onProgress called after each listed folder
     * @return int the number of unused files
     * @throws Mage_Core_Exception when the type is unknown or its folder cannot be read
     */
    public function scan(string $type, ?callable $onProgress = null): int
    {
        $helper = Mage::helper('mediacleaner');
        $directory = $helper->getStorageDirByType($type);
        if (!isset($this->getTypes()[$type]) || $directory === null) {
            Mage::throwException($helper->__('Unknown scan type.'));
        }

        $mount = Mage::getStorage('media');
        try {
            // Only a local disk has real folders. On a bucket, a folder without files is an empty listing
            if ($mount->isLocal() && !$mount->directoryExists($directory)) {
                Mage::throwException($helper->__('"%s" folder does not exist.', 'media/' . $directory));
            }
            $unused = match ($type) {
                'product'       => $this->findUnusedProductFiles($helper->listScannedFiles($mount, $directory, $onProgress)),
                'category'      => $this->findUnusedCategoryFiles($helper->listScannedFiles($mount, $directory, $onProgress)),
                'wysiwyg'       => $this->findUnusedWysiwygFiles($helper->listScannedFiles($mount, $directory, $onProgress)),
                default         => $helper->findUnusedProductCacheFiles($mount, $onProgress),
            };
        } catch (FilesystemException $e) {
            Mage::logException($e);
            Mage::throwException($helper->__('It was not possible to read the "%s" folder.', 'media/' . $directory));
        }

        $this->storeUnusedImages($type, $unused);
        return count($unused);
    }

    /**
     * @param list<string> $files paths relative to catalog/category
     * @return list<string>
     */
    protected function findUnusedCategoryFiles(array $files): array
    {
        $entityTypeId = Mage::getModel('catalog/category')->getResource()->getTypeId();
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');

        $attributeIds = $db->fetchCol($db->select()
            ->from($resource->getTableName('eav/attribute'), 'attribute_id')
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('frontend_input = ?', 'image'));

        $dbImages = [];
        if ($attributeIds) {
            $dbImages = $db->fetchCol($db->select()
                ->from($resource->getTableName('catalog_category_entity_varchar'), 'value')
                ->where('value IS NOT NULL')
                ->where('LENGTH(value) > 0')
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('attribute_id IN (?)', $attributeIds));
        }

        return array_values(array_diff($files, $dbImages));
    }

    /**
     * @param list<string> $files paths relative to catalog/product
     * @return list<string>
     */
    protected function findUnusedProductFiles(array $files): array
    {
        $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $removeLeadingSlash = fn(string $path): string => ltrim($path, '/');

        $attributeIds = $db->fetchCol($db->select()
            ->from($resource->getTableName('eav/attribute'), 'attribute_id')
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('frontend_input = ?', 'media_image'));

        $dbImages = [];
        if ($attributeIds) {
            $dbImages = array_map($removeLeadingSlash, $db->fetchCol($db->select()
                ->from($resource->getTableName('catalog_product_entity_varchar'), 'value')
                ->where('value IS NOT NULL')
                ->where('LENGTH(value) > 0')
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('attribute_id IN (?)', $attributeIds)
                ->where('value <> ?', 'no_selection')));
        }

        $placeholders = $db->fetchCol($db->select()
            ->distinct()
            ->from($resource->getTableName('core/config_data'), 'value')
            ->where('path LIKE ?', 'catalog/placeholder/%_placeholder'));
        foreach ($placeholders as $placeholder) {
            $dbImages[] = "placeholder/{$placeholder}";
        }

        $mediaGallery = array_map($removeLeadingSlash, $db->fetchCol($db->select()
            ->from($resource->getTableName('catalog/product_attribute_media_gallery'), 'value')
            ->where('value IS NOT NULL')
            ->where('LENGTH(value) > 0')));

        return array_values(array_diff($files, $dbImages, $mediaGallery));
    }

    /**
     * @param list<string> $files paths relative to wysiwyg
     * @return list<string>
     */
    protected function findUnusedWysiwygFiles(array $files): array
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $helper = Mage::helper('mediacleaner');

        $contents = array_merge(
            $db->fetchCol($db->select()->from($resource->getTableName('cms/page'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('cms/block'), 'content')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_text')),
            $db->fetchCol($db->select()->from($resource->getTableName('core/email_template'), 'template_styles')),
            $helper->getAllCSSFilesContents(),
        );

        return $helper->getUnusedWysiwygFiles(
            $files,
            array_map(strval(...), $contents),
            Mage::getStoreConfigFlag('configswatches/general/enabled'),
        );
    }

    /**
     * Replace the results of $type, so a file that is used again drops off the list.
     *
     * @param list<string> $unusedImages
     */
    protected function storeUnusedImages(string $type, array $unusedImages): void
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_write');
        $table = $resource->getTableName('mediacleaner/image');

        $db->delete($table, ['type = ?' => $type]);
        foreach ($unusedImages as $path) {
            $db->insertIgnore($table, ['type' => $type, 'path' => $path]);
        }
    }
}
