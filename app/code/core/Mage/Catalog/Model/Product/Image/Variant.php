<?php

/**
 * The option sets that templates render for product images, by variant path.
 *
 * The cache path of a resized image holds a hash of its options, and a hash cannot be
 * reversed. The image helper records each new option set here the first time a template
 * renders it, so the image route can rebuild the image from the path alone. The route
 * serves only a recorded variant, so a client cannot fill the disk with sizes that no
 * template uses.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_Variant
{
    public const CACHE_ID = 'catalog_product_image_variants';

    /** @var array<string, array{store_id: int, destination_subdir: string, params: array<string, mixed>}>|null */
    protected ?array $variants = null;

    /**
     * Record the options of $image for the current store. A known variant costs no query.
     */
    public function register(Mage_Catalog_Model_Product_Image $image): void
    {
        $params = $image->getTransformParams();
        unset($params['_sourceFile']);
        $path = Maho::buildImageResizeVariantPath($params);
        if (isset($this->getVariants()[$path])) {
            return;
        }

        $storeId = (int) Mage::app()->getStore()->getId();
        $destinationSubdir = (string) $params['_destinationSubdir'];
        $this->getResource()->add($path, $storeId, $destinationSubdir, $params);
        Mage::app()->removeCache(self::CACHE_ID);
        $this->variants[$path] = [
            'store_id' => $storeId,
            'destination_subdir' => $destinationSubdir,
            'params' => $params,
        ];
    }

    /**
     * @return list<array<string, mixed>> the params of every variant of $destinationSubdir in the store
     */
    public function getParamsFor(int $storeId, string $destinationSubdir): array
    {
        $result = [];
        foreach ($this->getVariants() as $variant) {
            if ($variant['store_id'] === $storeId && $variant['destination_subdir'] === $destinationSubdir) {
                $result[] = $variant['params'];
            }
        }
        return $result;
    }

    /**
     * Build the image that a mount path below catalog/product/cache names.
     *
     * Return null when the path names no recorded variant, when its file name does not end
     * with the configured image extension, or when its source leaves catalog/product. The
     * current store becomes the store of the variant, because the output extension and the
     * watermark come from the store config.
     */
    public function createImage(string $cacheKey): ?Mage_Catalog_Model_Product_Image
    {
        $prefix = Mage_Catalog_Model_Product_Image::CACHE_DIRECTORY . '/';
        if (!str_starts_with($cacheKey, $prefix)) {
            return null;
        }

        $segments = explode('/', substr($cacheKey, strlen($prefix)));
        foreach ([4, 3] as $length) {
            $variant = $this->getVariants()[implode('/', array_slice($segments, 0, $length))] ?? null;
            if ($variant === null || count($segments) <= $length) {
                continue;
            }

            Mage::app()->setCurrentStore($variant['store_id']);
            $file = implode('/', array_slice($segments, $length));
            $extension = Maho::getConfiguredImageExtension();
            if (!str_ends_with($file, $extension)) {
                return null;
            }

            $sourceFile = $this->getSourceFile(substr($file, 0, -strlen($extension)));
            if ($sourceFile === null) {
                return null;
            }

            /** @var Mage_Catalog_Model_Product_Image $image */
            $image = Mage::getModel('catalog/product_image');
            $image->setTransformParams($variant['params'])->setBaseFile($sourceFile);

            return $image->getCacheKey() === $cacheKey ? $image : null;
        }

        return null;
    }

    /**
     * The path below catalog/product that $file names, such as /i/m/image.jpg. Return null when
     * $file leaves catalog/product or names a file in the resize cache.
     */
    public function getSourceFile(string $file): ?string
    {
        $baseDir = Mage::getSingleton('catalog/product_media_config')->getBaseMediaStoragePath();
        $sourceKey = \Maho\Io::getPathWithinMount(Mage::getStorage('media'), $baseDir, $file);
        if ($sourceKey === null || str_starts_with($sourceKey, Mage_Catalog_Model_Product_Image::CACHE_DIRECTORY . '/')) {
            return null;
        }
        return substr($sourceKey, strlen($baseDir));
    }

    /**
     * Delete the resized copies of $sourceFile in every recorded variant. The delete of a
     * missing file is no error, so no listing of the cache is necessary.
     *
     * @param string $sourceFile path below catalog/product, such as /i/m/image.jpg
     */
    public function deleteCachedCopies(string $sourceFile): void
    {
        $mount = Mage::getStorage('media');
        $extension = Maho::getConfiguredImageExtension();
        foreach (array_keys($this->getVariants()) as $path) {
            $key = \Maho\Io::getPathWithinMount(
                $mount,
                Mage_Catalog_Model_Product_Image::CACHE_DIRECTORY . '/' . $path,
                $sourceFile . $extension,
            );
            if ($key !== null) {
                $mount->delete($key);
            }
        }
    }

    /**
     * @return array<string, array{store_id: int, destination_subdir: string, params: array<string, mixed>}>
     */
    protected function getVariants(): array
    {
        if ($this->variants !== null) {
            return $this->variants;
        }

        $cached = Mage::app()->loadCache(self::CACHE_ID);
        $variants = is_string($cached) && $cached !== '' ? json_decode($cached, true) : null;
        if (!is_array($variants)) {
            $variants = $this->getResource()->loadAll();
            Mage::app()->saveCache(
                Mage::helper('core')->jsonEncode($variants),
                self::CACHE_ID,
                [Mage_Catalog_Model_Product_Image::CACHE_TAG],
            );
        }

        return $this->variants = $variants;
    }

    protected function getResource(): Mage_Catalog_Model_Resource_Product_Image_Variant
    {
        return Mage::getResourceSingleton('catalog/product_image_variant');
    }
}
