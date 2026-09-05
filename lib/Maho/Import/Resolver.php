<?php

/**
 * Lookups by code for the importers, memoised for one run and reset when stores or config change.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import;

use Mage;

final class Resolver
{
    /** @var array<string, array<string, int|null>> */
    private array $cache = [];

    public function reset(): void
    {
        $this->cache = [];
    }

    public function websiteId(string $code): int
    {
        return $this->remember('website', $code, function () use ($code): ?int {
            $id = Mage::getModel('core/website')->load($code, 'code')->getId();
            return $id ? (int) $id : null;
        }) ?? throw new \InvalidArgumentException("unknown website code '$code'");
    }

    public function storeId(string $code): int
    {
        return $this->remember('store', $code, function () use ($code): ?int {
            $id = Mage::getModel('core/store')->load($code, 'code')->getId();
            return $id === null || $id === '' ? null : (int) $id;
        }) ?? throw new \InvalidArgumentException("unknown store code '$code'");
    }

    /**
     * A pipe list of store codes; an empty list means every store (id 0).
     *
     * @return list<int>
     */
    public function storeIds(string $codes): array
    {
        $list = CsvFile::list($codes);
        if ($list === []) {
            return [0];
        }
        return array_map($this->storeId(...), $list);
    }

    public function scopeId(string $scope, string $scopeCode): int
    {
        return match ($scope) {
            'default' => 0,
            'websites' => $this->websiteId($scopeCode),
            'stores' => $this->storeId($scopeCode),
            default => throw new \InvalidArgumentException("unknown scope '$scope'"),
        };
    }

    public function attributeId(string $code, string $entityType = 'catalog_product'): int
    {
        return $this->remember("attribute:$entityType", $code, function () use ($code, $entityType): ?int {
            $id = Mage::getSingleton('eav/config')->getAttribute($entityType, $code)?->getId();
            return $id ? (int) $id : null;
        }) ?? throw new \InvalidArgumentException("unknown attribute code '$code'");
    }

    public function attributeSetId(string $name, string $entityType = 'catalog_product'): int
    {
        return $this->remember("attribute_set:$entityType", $name, function () use ($name, $entityType): ?int {
            $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType($entityType)->getId();
            $id = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $name)
                ->getFirstItem()
                ->getId();
            return $id ? (int) $id : null;
        }) ?? throw new \InvalidArgumentException("unknown attribute set '$name'");
    }

    /**
     * A level-1 category (a store root) by name.
     */
    public function rootCategoryId(string $name): ?int
    {
        return $this->remember('root_category', $name, function () use ($name): ?int {
            $id = Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToFilter('level', 1)
                ->addAttributeToFilter('name', $name)
                ->getFirstItem()
                ->getId();
            return $id ? (int) $id : null;
        });
    }

    /**
     * A category by root name and a slash path of url keys below it; an empty path is the root.
     */
    public function categoryId(string $rootName, string $path): ?int
    {
        $rootId = $this->rootCategoryId($rootName);
        if ($rootId === null) {
            return null;
        }
        $parentId = $rootId;
        foreach (CsvFile::list(str_replace('/', '|', $path)) as $urlKey) {
            $parentId = $this->remember("category:$parentId", $urlKey, function () use ($parentId, $urlKey): ?int {
                $id = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToFilter('parent_id', $parentId)
                    ->addAttributeToFilter('url_key', $urlKey)
                    ->getFirstItem()
                    ->getId();
                return $id ? (int) $id : null;
            });
            if ($parentId === null) {
                return null;
            }
        }
        return $parentId;
    }

    public function cmsBlockId(string $identifier): ?int
    {
        return $this->remember('cms_block', $identifier, function () use ($identifier): ?int {
            $id = Mage::getModel('cms/block')->load($identifier, 'identifier')->getId();
            return $id ? (int) $id : null;
        });
    }

    /**
     * @param callable(): ?int $lookup
     */
    private function remember(string $kind, string $key, callable $lookup): ?int
    {
        if (!array_key_exists($key, $this->cache[$kind] ?? [])) {
            $this->cache[$kind][$key] = $lookup();
        }
        return $this->cache[$kind][$key];
    }

    public const MACRO = '/\{\{(attribute_id|attribute_ids|category_id|cms_block_id|store_id|website_id):([^}]*)\}\}/';

    /**
     * Expands {{attribute_id:code}}, {{attribute_ids:a,b}}, {{category_id:Root/url-key/...}}, {{cms_block_id:identifier}},
     * {{store_id:code}} and {{website_id:code}}; a lenient run leaves a macro it cannot resolve in place.
     */
    public function expand(string $value, bool $lenient = false): string
    {
        return (string) preg_replace_callback(self::MACRO, function (array $match) use ($lenient): string {
            $argument = trim($match[2]);
            try {
                return $this->macro($match[1], $argument);
            } catch (\InvalidArgumentException $e) {
                if ($lenient) {
                    return $match[0];
                }
                throw $e;
            }
        }, $value);
    }

    private function macro(string $name, string $argument): string
    {
        return match ($name) {
            'attribute_id' => (string) $this->attributeId($argument),
            'attribute_ids' => implode(',', array_map(fn(string $code) => (string) $this->attributeId(trim($code)), explode(',', $argument))),
            'category_id' => (string) ($this->categoryByArgument($argument) ?? throw new \InvalidArgumentException("unknown category '$argument'")),
            'cms_block_id' => (string) ($this->cmsBlockId($argument) ?? throw new \InvalidArgumentException("unknown cms block '$argument'")),
            'store_id' => (string) $this->storeId($argument),
            'website_id' => (string) $this->websiteId($argument),
            default => throw new \InvalidArgumentException("unknown macro '$name'"),
        };
    }

    private function categoryByArgument(string $argument): ?int
    {
        [$root, $path] = array_pad(explode('/', $argument, 2), 2, '');
        return $this->categoryId($root, $path);
    }
}
