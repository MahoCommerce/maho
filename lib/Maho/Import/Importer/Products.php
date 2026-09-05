<?php

/**
 * Products in the Mage_ImportExport layout, with pictures read from a media dir of the author's choice.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\CsvFile;

class Products extends AbstractImportExportImporter
{
    public const OPTION_MEDIA_DIR = 'media_dir';

    private const IMAGE_COLUMNS = ['_media_image', 'image', 'small_image', 'thumbnail'];

    #[\Override]
    protected function entityCode(): string
    {
        return 'catalog_product';
    }

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['sku'];
    }

    #[\Override]
    protected function injectedColumns(): array
    {
        return ['_media_attribute_id' => (string) $this->resolver->attributeId('media_gallery')];
    }

    #[\Override]
    protected function normalize(CsvFile $file, array $options): array
    {
        $options[self::OPTION_MEDIA_DIR] = rtrim($options[self::OPTION_MEDIA_DIR] ?? dirname($file->getPath()) . '/media/import', '/');
        return $options;
    }

    #[\Override]
    protected function checkRow(CsvFile $file, int $line, array $row, array $options): void
    {
        $isProduct = ($row['sku'] ?? '') !== '';
        if ($isProduct) {
            $type = $row['_type'] ?? '';
            $types = array_keys(Mage::getConfig()->getNode(\Mage_ImportExport_Model_Import_Entity_Product::CONFIG_KEY_PRODUCT_TYPES)->asCanonicalArray());
            if ($type !== '' && !in_array($type, $types, true)) {
                $this->fail($file, $line, "_type '$type' is not one of " . implode(', ', $types));
            }
            if (($row['_attribute_set'] ?? '') !== '') {
                $this->at($file, $line, fn() => $this->resolver->attributeSetId($row['_attribute_set']));
            }
        }
        if (($row['_category'] ?? '') !== '' && ($row['_root_category'] ?? '') === '') {
            $this->fail($file, $line, '_root_category is required on every row that sets _category');
        }
        if (($row['_root_category'] ?? '') !== '' && $this->resolver->rootCategoryId($row['_root_category']) === null) {
            $this->fail($file, $line, "unknown root category '{$row['_root_category']}'");
        }
        if (($row['_product_websites'] ?? '') !== '') {
            $this->at($file, $line, fn() => $this->resolver->websiteId($row['_product_websites']));
        }
        if (($row['_store'] ?? '') !== '') {
            $this->at($file, $line, fn() => $this->resolver->storeId($row['_store']));
        }
        foreach (self::IMAGE_COLUMNS as $column) {
            $image = $row[$column] ?? '';
            if ($image === '') {
                continue;
            }
            $path = rtrim($options[self::OPTION_MEDIA_DIR], '/') . '/' . ltrim($image, '/');
            if (!is_file($path)) {
                $this->fail($file, $line, "$column '$image' not found in " . $options[self::OPTION_MEDIA_DIR]);
            }
            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['webp', 'avif', 'jpg', 'jpeg', 'gif', 'png'], true)) {
                $this->fail($file, $line, "$column '$image' is not a webp, avif, jpg, jpeg, gif or png file");
            }
        }
    }
}
