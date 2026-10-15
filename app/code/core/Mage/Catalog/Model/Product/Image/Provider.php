<?php

/**
 * The source files of the product image entity: the main images, the gallery images and the
 * placeholders.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_Provider implements Mage_Core_Model_Image_ProviderInterface
{
    public const ENTITY = 'product';

    /**
     * Queue the creation of the files of the images of the products.
     *
     * @param array<int|string> $productIds
     */
    public function queue(array $productIds): void
    {
        if ($productIds !== []) {
            Mage::getSingleton('core/image_resizer')->queue(self::ENTITY, $this->getProductImageFiles($productIds));
        }
    }

    /**
     * Every source file of the entity, for storage:image:resize.
     *
     * @return iterable<string>
     */
    #[\Override]
    public function getImageFiles(): iterable
    {
        yield from $this->getPlaceholderFiles();
        yield from $this->getProductImageFiles();
    }

    /**
     * The placeholder of the image role from the config, or the placeholder of the skin.
     */
    #[\Override]
    public function getPlaceholderUrl(int $width): ?string
    {
        return (string) Mage::helper('catalog/image')->init(Mage::getModel('catalog/product'), 'image')->resize($width);
    }

    /**
     * The role and gallery images of the products, or of every product when $productIds is null.
     *
     * @param array<int|string>|null $productIds
     * @return list<string>
     */
    public function getProductImageFiles(?array $productIds = null): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $ids = $productIds === null ? null : array_map(intval(...), $productIds);

        $gallery = $adapter->select()
            ->distinct()
            ->from($resource->getTableName(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE), ['value']);
        if ($ids !== null) {
            $gallery->where('entity_id IN (?)', $ids);
        }

        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'image');
        $images = $adapter->select()
            ->distinct()
            ->from($resource->getTableName(['catalog/product', 'varchar']), ['value'])
            ->where('attribute_id = ?', $attribute ? (int) $attribute->getId() : 0);
        if ($ids !== null) {
            $images->where('entity_id IN (?)', $ids);
        }

        $files = [...$adapter->fetchCol($gallery), ...$adapter->fetchCol($images)];
        return array_values(array_unique(array_filter(
            array_map(strval(...), $files),
            fn(string $file): bool => $file !== '' && $file !== 'no_selection',
        )));
    }

    /**
     * The placeholder images that the config of any scope names, below catalog/product.
     *
     * @return list<string>
     */
    public function getPlaceholderFiles(): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $values = $adapter->fetchCol($adapter->select()
            ->distinct()
            ->from($resource->getTableName('core/config_data'), ['value'])
            ->where('path LIKE ?', 'catalog/placeholder/%_placeholder'));

        return array_values(array_map(
            fn(string $value): string => '/' . Mage_Core_Model_Image_Resizer::PLACEHOLDER_FOLDER . '/' . ltrim($value, '/'),
            array_filter(array_map(strval(...), $values), fn(string $value): bool => $value !== ''),
        ));
    }
}
