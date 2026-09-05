<?php

/**
 * Categories keyed by root name and a slash path of url keys, with store-scoped override rows.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class Categories extends AbstractImporter
{
    public const OPTION_MEDIA_DIR = 'media_dir';

    private const DISPLAY_MODES = [
        \Mage_Catalog_Model_Category::DM_PRODUCT,
        \Mage_Catalog_Model_Category::DM_PAGE,
        \Mage_Catalog_Model_Category::DM_MIXED,
    ];

    private const TEXT_COLUMNS = ['name', 'description', 'meta_title', 'meta_keywords', 'meta_description'];
    private const FLAG_COLUMNS = ['is_active', 'include_in_menu', 'is_anchor'];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['root', 'path'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        $seen = [];
        foreach ($file as $line => $row) {
            $root = $this->requireValue($file, $line, $row, 'root');
            $path = trim($row['path'], '/');
            $segments = $path === '' ? [] : explode('/', $path);
            foreach ($segments as $segment) {
                if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $segment)) {
                    $this->fail($file, $line, "path segment '$segment' must be a url key (lowercase letters, digits, dashes)");
                }
            }
            $row['path'] = $path;
            $row['depth'] = count($segments);
            $row['store_id'] = null;
            if (($row['store_code'] ?? '') !== '') {
                $row['store_id'] = $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
            } else {
                if (isset($seen["$root/$path"])) {
                    $this->fail($file, $line, "category '$root/$path' appears twice");
                }
                $seen["$root/$path"] = true;
                if ($path !== '' && ($row['name'] ?? '') === '' && $this->resolver->categoryId($root, $path) === null) {
                    $this->fail($file, $line, 'name is required for a new category');
                }
            }
            foreach (self::FLAG_COLUMNS as $flag) {
                $row[$flag] = ($row[$flag] ?? '') === '' ? null : $this->at($file, $line, fn() => CsvFile::bool($row[$flag], true));
            }
            if (($row['display_mode'] ?? '') !== '' && !in_array($row['display_mode'], self::DISPLAY_MODES, true)) {
                $this->fail($file, $line, "display_mode '{$row['display_mode']}' is not one of " . implode(', ', self::DISPLAY_MODES));
            }
            if (($row['landing_page'] ?? '') !== '') {
                $row['landing_page_id'] = $this->resolver->cmsBlockId($row['landing_page'])
                    ?? $this->fail($file, $line, "unknown cms block '{$row['landing_page']}' (blocks import before categories)");
            }
            if (($row['image'] ?? '') !== '') {
                $source = $this->imagePath($file, $options, $row['image']);
                if (!is_file($source)) {
                    $this->fail($file, $line, "image '{$row['image']}' not found in " . dirname($source));
                }
            }
            $rows[$line] = $row;
        }
        foreach ($rows as $line => $row) {
            if ($row['path'] !== '' && !isset($seen[$row['root'] . '/']) && $this->resolver->rootCategoryId($row['root']) === null) {
                $this->fail($file, $line, "unknown root category '{$row['root']}' (a row with an empty path creates it)");
            }
        }
        uasort($rows, static fn(array $a, array $b) => [$a['depth'], $a['store_id'] !== null] <=> [$b['depth'], $b['store_id'] !== null]);
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $done = 0;
        foreach ($rows as $line => $row) {
            $category = $this->find($file, $line, $row, $result);
            $this->apply($category, $row, $file, $options);
            $isNew = !$category->getId();
            $category->save();
            if ($isNew && ($row['position'] ?? '') !== '' && (int) $category->getPosition() !== (int) $row['position']) {
                $category->setPosition((int) $row['position'])->save();
            }
            $this->resolver->reset();
            $reporter->progress(++$done, count($rows), $row['root'] . '/' . $row['path']);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function find(CsvFile $file, int $line, array $row, Result $result): \Mage_Catalog_Model_Category
    {
        $storeId = $row['store_id'] ?? 0;
        $id = $this->resolver->categoryId($row['root'], $row['path']);
        if ($id !== null) {
            $result->updated++;
            return Mage::getModel('catalog/category')->setStoreId($storeId)->load($id);
        }
        if ($row['store_id'] !== null) {
            $this->fail($file, $line, "category '{$row['root']}/{$row['path']}' does not exist, a store row cannot create it");
        }
        $category = Mage::getModel('catalog/category')->setStoreId(0);
        $category->setAttributeSetId($category->getDefaultAttributeSetId());
        if ($row['path'] === '') {
            $category->setPath((string) \Mage_Catalog_Model_Category::TREE_ROOT_ID);
        } else {
            $segments = explode('/', $row['path']);
            $urlKey = array_pop($segments);
            $parentId = $this->resolver->categoryId($row['root'], implode('/', $segments))
                ?? $this->fail($file, $line, "parent of '{$row['path']}' does not exist");
            $category->setPath(Mage::getModel('catalog/category')->load($parentId)->getPath())->setUrlKey($urlKey);
        }
        $category->setIsActive(1)->setIncludeInMenu(1)->setIsAnchor(1)->setDisplayMode(\Mage_Catalog_Model_Category::DM_PRODUCT);
        $result->created++;
        return $category;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $options
     */
    private function apply(\Mage_Catalog_Model_Category $category, array $row, CsvFile $file, array $options): void
    {
        foreach (self::TEXT_COLUMNS as $column) {
            if (($row[$column] ?? '') !== '') {
                $category->setData($column, $row[$column]);
            }
        }
        foreach (self::FLAG_COLUMNS as $flag) {
            if ($row[$flag] !== null) {
                $category->setData($flag, $row[$flag] ? 1 : 0);
            }
        }
        if (($row['position'] ?? '') !== '') {
            $category->setPosition((int) $row['position']);
        }
        if (($row['display_mode'] ?? '') !== '') {
            $category->setDisplayMode($row['display_mode']);
        }
        if (isset($row['landing_page_id'])) {
            $category->setLandingPage($row['landing_page_id']);
        }
        if (($row['image'] ?? '') !== '') {
            $source = $this->imagePath($file, $options, $row['image']);
            $target = Mage::getBaseDir('media') . '/catalog/category/' . basename($source);
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            copy($source, $target);
            $category->setImage(basename($source));
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function imagePath(CsvFile $file, array $options, string $image): string
    {
        $dir = $options[self::OPTION_MEDIA_DIR] ?? dirname($file->getPath()) . '/media/catalog/category';
        return rtrim($dir, '/') . '/' . ltrim($image, '/');
    }
}
