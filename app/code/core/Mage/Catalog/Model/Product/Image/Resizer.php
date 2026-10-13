<?php

/**
 * Creates the recorded sizes of product images before a visitor asks for them.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_Resizer
{
    public const QUEUE_NAME = 'catalog_image';

    /** Product ids in one queue message. */
    public const BATCH_SIZE = 100;

    /** The image attributes that a template renders with the destination subdir of the same name. */
    public const ROLES = ['image', 'small_image', 'thumbnail'];

    /** The destination subdirs that the product page renders a gallery image with. */
    public const GALLERY_ROLES = ['image', 'thumbnail'];

    /**
     * Queue the resize of the products. The resize only saves time, so an error is logged
     * and does not stop the caller. Without it, the image route creates each size on the
     * first request.
     *
     * @param array<int|string> $productIds
     */
    public function queue(array $productIds): void
    {
        if ($productIds === [] || !Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return;
        }

        try {
            foreach (array_chunk(array_map(intval(...), $productIds), self::BATCH_SIZE) as $batch) {
                \Maho\Queue\QueueManager::dispatch(
                    new Mage_Catalog_Model_Product_Image_ResizeMessage($batch),
                    queue: self::QUEUE_NAME,
                );
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Resize each role image of each product to every size that the store views of its
     * websites recorded for that role, and each gallery image to the sizes of the gallery
     * roles. A size that is in the cache already is skipped.
     *
     * @param list<int> $productIds
     * @return int the number of images that were resized
     */
    public function resizeProducts(array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $app = Mage::app();
        $sizes = Mage::getModel('catalog/product_image_size');
        $initialStoreId = (int) $app->getStore()->getId();
        $galleryFiles = $this->getGalleryFiles($productIds);
        $count = 0;

        try {
            foreach ($app->getStores() as $store) {
                $storeId = (int) $store->getId();
                $app->setCurrentStore($storeId);
                $products = Mage::getResourceModel('catalog/product_collection')
                    ->setStoreId($storeId)
                    ->addWebsiteFilter($store->getWebsiteId())
                    ->addIdFilter($productIds)
                    ->addAttributeToSelect(self::ROLES);
                foreach ($products as $product) {
                    $files = [];
                    foreach (self::ROLES as $role) {
                        $files[$role] = [$product->getData($role)];
                    }
                    foreach (self::GALLERY_ROLES as $role) {
                        array_push($files[$role], ...$galleryFiles[(int) $product->getId()] ?? []);
                    }
                    $fileSizes = [];
                    foreach ($files as $role => $roleFiles) {
                        $params = $sizes->getParamsFor($storeId, $role);
                        foreach (array_unique(array_filter($roleFiles, is_string(...))) as $file) {
                            if ($file !== '' && $file !== 'no_selection') {
                                $fileSizes[$file] = array_merge($fileSizes[$file] ?? [], $params);
                            }
                        }
                    }
                    foreach ($fileSizes as $file => $params) {
                        $count += $this->resizeFile((string) $file, $params);
                    }
                }
            }
        } finally {
            $app->setCurrentStore($initialStoreId);
        }

        return $count;
    }

    /**
     * @param list<int> $productIds
     * @return array<int, list<string>> the gallery files of each product, by product id
     */
    protected function getGalleryFiles(array $productIds): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $rows = $adapter->fetchAll($adapter->select()
            ->from($resource->getTableName(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE), ['entity_id', 'value'])
            ->where('entity_id IN (?)', $productIds));

        $files = [];
        foreach ($rows as $row) {
            $files[(int) $row['entity_id']][] = (string) $row['value'];
        }
        return $files;
    }

    /**
     * @param list<array<string, mixed>> $sizes
     */
    protected function resizeFile(string $file, array $sizes): int
    {
        if (Mage::getSingleton('catalog/product_image_size')->resolveSourceFile($file) === null) {
            return 0;
        }
        $count = 0;
        $source = null;
        foreach ($sizes as $params) {
            /** @var Mage_Catalog_Model_Product_Image $image */
            $image = Mage::getModel('catalog/product_image');
            $image->setTransformParams($params)->setBaseFile($file);
            if ($image->getResizedStoragePath() === null || $image->isCached()) {
                continue;
            }
            if ($source === null && !$image->sourceExists()) {
                return $count;
            }

            try {
                if ($image->getMount()->localRoot() === null) {
                    $image->setSourceBinary($source ??= $image->getSourceBinary());
                }
                $image->saveFile();
                $count++;
            } catch (\Throwable $e) {
                Mage::logException($e);
                return $count;
            }
        }
        return $count;
    }
}
