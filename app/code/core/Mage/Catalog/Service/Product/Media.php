<?php

/**
 * Check, add, change and remove the images of the gallery of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;
use Mage_Catalog_Model_Product_Attribute_Backend_Media as MediaBackend;

class Mage_Catalog_Service_Product_Media
{
    /** The extensions that a temporary image file can take. Another extension becomes jpg. */
    private const TEMP_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /**
     * Check that the file $path is an image that the gallery accepts: a known image type, no side longer than the
     * configured maximum, and a new copy of the pixels in place of the file, so that hidden data is dropped. The admin
     * upload uses this method as a validator of its uploader.
     *
     * @throws Mage_Core_Exception
     */
    public function validateImage(string $path): void
    {
        Mage::helper('catalog/image')->validateUploadFile($path);
        Mage::getModel('core/file_validator_image')->validate($path);
    }

    /**
     * Check the image file $path, add it to the gallery of $product and save the product.
     *
     * @param list<string> $types the roles of the image, such as image, small_image and thumbnail
     * @throws Mage_Core_Exception when the file is not an image that the gallery accepts
     */
    public function addImage(
        Product $product,
        string $path,
        array $types = [],
        ?string $label = null,
        ?int $position = null,
        ?bool $disabled = null,
    ): void {
        $this->validateImage($path);
        $types = Mage_Catalog_Helper_Image::mapDeprecatedRoles($types);
        $backend = $this->getBackend($product);
        $file = $backend->addImage($product, $path, $types === [] ? null : $types, false, false);
        $backend->updateImage($product, $file, $this->getImageData($label, $position, $disabled));
        $this->save($product);
    }

    /**
     * Add the image $data, the content of a file named $fileName, to the gallery of $product and save the product.
     *
     * @param list<string> $types the roles of the image
     * @throws Mage_Core_Exception when the data is not an image that the gallery accepts
     * @throws RuntimeException when the temporary file cannot be written
     */
    public function addImageFromData(
        Product $product,
        string $data,
        string $fileName,
        array $types = [],
        ?string $label = null,
        ?int $position = null,
        ?bool $disabled = null,
    ): void {
        // The extension names the file before the image check, so a file name from the client cannot give it .php
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $extension = in_array($extension, self::TEMP_EXTENSIONS, true) ? $extension : 'jpg';
        $tmpPath = tempnam(Mage::getBaseDir('tmp'), 'maho_media_');
        if ($tmpPath === false || !rename($tmpPath, $tmpPath . '.' . $extension)) {
            throw new RuntimeException('Failed to create a temporary file for the image');
        }
        $path = $tmpPath . '.' . $extension;
        try {
            file_put_contents($path, $data);
            $this->addImage($product, $path, $types, $label, $position, $disabled);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Download the image at $url and add it to the gallery of $product. The URL must pass the checks of OutboundUrl.
     *
     * @param list<string> $types the roles of the image
     * @throws Mage_Core_Exception_Input when the URL is not allowed or the download fails
     * @throws Mage_Core_Exception when the file is not an image that the gallery accepts
     */
    public function addImageFromUrl(
        Product $product,
        string $url,
        array $types = [],
        ?string $label = null,
        ?int $position = null,
        ?bool $disabled = null,
    ): void {
        $helper = Mage::helper('catalog');
        try {
            $target = new \Maho\Security\OutboundUrl()->validate($url);
        } catch (\Maho\Security\OutboundUrlException $e) {
            throw new Mage_Core_Exception_Input()->addError('image_url', $helper->__('Invalid imageUrl: %s', $e->getMessage()));
        }
        $data = @file_get_contents($target->pinnedUrl(), false, stream_context_create($target->streamContextOptions(10)));
        if ($data === false) {
            throw new Mage_Core_Exception_Input()->addError('image_url', $helper->__('Failed to download image from URL'));
        }
        $this->addImageFromData($product, $data, $target->path, $types, $label, $position, $disabled);
    }

    /**
     * Change the label, the position, the disabled flag and the roles of the gallery image $valueId. A null argument
     * keeps the current value.
     *
     * @param list<string>|null $types the roles to give to the image
     * @throws Mage_Core_Exception_Input when $valueId is not a positive ID
     * @throws Mage_Core_Exception_NoSuchEntity when the gallery of $product has no image $valueId
     */
    public function updateImage(
        Product $product,
        int $valueId,
        ?string $label = null,
        ?int $position = null,
        ?bool $disabled = null,
        ?array $types = null,
    ): void {
        $file = $this->getFile($product, $valueId);
        $backend = $this->getBackend($product);
        $backend->updateImage($product, $file, $this->getImageData($label, $position, $disabled));
        if ($types !== null) {
            $backend->setMediaAttribute($product, $types, $file);
        }
        $this->save($product);
    }

    /**
     * @throws Mage_Core_Exception_Input when $valueId is not a positive ID
     * @throws Mage_Core_Exception_NoSuchEntity when the gallery of $product has no image $valueId
     */
    public function removeImage(Product $product, int $valueId): void
    {
        $this->getBackend($product)->removeImage($product, $this->getFile($product, $valueId));
        $this->save($product);
    }

    /**
     * @throws Mage_Core_Exception_Input when $valueId is not a positive ID
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function getFile(Product $product, int $valueId): string
    {
        if ($valueId <= 0) {
            throw new Mage_Core_Exception_Input()->addError('value_id', Mage::helper('catalog')->__('valueId is required'));
        }
        foreach ($product->getData('media_gallery')['images'] ?? [] as $image) {
            if ((int) ($image['value_id'] ?? 0) === $valueId && isset($image['file'])) {
                return (string) $image['file'];
            }
        }
        throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Gallery image not found'));
    }

    /**
     * @return array{label?: string, position?: int, disabled?: int}
     */
    private function getImageData(?string $label, ?int $position, ?bool $disabled): array
    {
        return array_filter(
            ['label' => $label, 'position' => $position, 'disabled' => $disabled === null ? null : (int) $disabled],
            fn(mixed $value): bool => $value !== null,
        );
    }

    private function getBackend(Product $product): MediaBackend
    {
        /** @var MediaBackend $backend */
        $backend = $product->getResource()->getAttribute('media_gallery')->getBackend();
        return $backend;
    }

    /**
     * Save $product without its stock data, which a gallery change does not change.
     */
    private function save(Product $product): void
    {
        $product->unsetData('stock_data');
        $product->save();
    }
}
