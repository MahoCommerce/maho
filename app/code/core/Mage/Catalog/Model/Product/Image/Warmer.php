<?php

/**
 * Creates the recorded sizes of product images before a visitor asks for them.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_Warmer
{
    public const QUEUE_NAME = 'catalog_image';

    /** Product ids in one queue message. */
    public const BATCH_SIZE = 100;

    /** The image attributes that a template renders with the destination subdir of the same name. */
    public const ROLES = ['image', 'small_image', 'thumbnail'];

    /**
     * Queue the warm-up of the products. The warm-up only saves time, so an error is logged
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
                    new Mage_Catalog_Model_Product_Image_WarmMessage($batch),
                    queue: self::QUEUE_NAME,
                );
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Resize each role image of each product to every size that the store views of its
     * websites recorded for that role. A size that is in the cache already is skipped.
     *
     * @param list<int> $productIds
     * @return int the number of images that were resized
     */
    public function warmProducts(array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $app = Mage::app();
        $variants = Mage::getSingleton('catalog/product_image_variant');
        $initialStoreId = (int) $app->getStore()->getId();
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
                    foreach (self::ROLES as $role) {
                        $file = $product->getData($role);
                        if (is_string($file) && $file !== '' && $file !== 'no_selection') {
                            $count += $this->warmFile($file, $variants->getParamsFor($storeId, $role));
                        }
                    }
                }
            }
        } finally {
            $app->setCurrentStore($initialStoreId);
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $variants
     */
    protected function warmFile(string $file, array $variants): int
    {
        $baseDir = Mage::getSingleton('catalog/product_media_config')->getBaseMediaStoragePath();
        if (\Maho\Io::getPathWithinMount(Mage::getStorage('media'), $baseDir, $file) === null) {
            return 0;
        }
        $count = 0;
        foreach ($variants as $params) {
            /** @var Mage_Catalog_Model_Product_Image $image */
            $image = Mage::getModel('catalog/product_image');
            $image->setTransformParams($params)->setBaseFile($file);
            if ($image->getCacheKey() === null || $image->isCached()) {
                continue;
            }
            if (!$image->sourceExists()) {
                return $count;
            }

            try {
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
