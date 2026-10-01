<?php

/**
 * Maps each category to its key and back. The key is the name of the root category and the url keys below it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

class Mage_ImportExport_Model_Category_KeyMap
{
    /** @var array<int, array{0: string, 1: string}> */
    protected array $keys = [];

    /** @var array<string, array<string, int>> */
    protected array $ids = [];

    /**
     * A category without a url key in its path has no key. Two siblings with the same url key share a key,
     * and the older one owns it.
     */
    public function __construct()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('level', ['gt' => 0])
            ->setOrder('entity_id', 'ASC');

        $names = [];
        $urlKeys = [];
        $paths = [];
        foreach ($collection as $category) {
            $id = (int) $category->getId();
            $names[$id] = (string) $category->getName();
            $urlKeys[$id] = (string) $category->getUrlKey();
            $paths[$id] = array_map(intval(...), array_slice(explode('/', (string) $category->getPath()), 1));
        }

        foreach ($paths as $id => $path) {
            $rootName = $names[array_shift($path)] ?? '';
            $segments = array_map(fn(int $ancestorId): string => $urlKeys[$ancestorId] ?? '', $path);
            if ($rootName !== '' && !in_array('', $segments, true)) {
                $this->add($id, $rootName, implode('/', $segments));
            }
        }
    }

    /**
     * @return array{0: string, 1: string}|null The root name and the path, or null when the category has no key.
     */
    public function getKey(int $categoryId): ?array
    {
        return $this->keys[$categoryId] ?? null;
    }

    /**
     * An empty path is the root category itself.
     */
    public function getId(string $rootName, string $path): ?int
    {
        return $this->ids[$rootName][$path] ?? null;
    }

    public function add(int $categoryId, string $rootName, string $path): void
    {
        $this->keys[$categoryId] = [$rootName, $path];
        $this->ids[$rootName][$path] ??= $categoryId;
    }
}
