<?php

/**
 * Search with the search sources of the admin global search, for the admin and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Service_GlobalSearch
{
    public const XML_PATH_ENABLE = 'admin/global_search/enable';
    public const XML_PATH_SOURCES = 'adminhtml/global_search';
    public const MIN_QUERY_LENGTH = 2;
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT = 25;

    /**
     * The search sources of the core, with the entity of their results. Only these results have a store or a website.
     */
    public const CORE_SOURCES = [
        Mage_Adminhtml_Model_Search_Order::class => 'order',
        Mage_Adminhtml_Model_Search_Customer::class => 'customer',
        Mage_Adminhtml_Model_Search_Catalog::class => 'product',
    ];

    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLE, Mage_Core_Model_App::ADMIN_STORE_ID);
    }

    /**
     * Run each search source that $canSearch allows, and return at most $limit results of each source.
     * A query with less than MIN_QUERY_LENGTH characters gives no results.
     *
     * @param Closure(string, ?string): bool $canSearch gets the ACL resource of a source ('' when it has none) and the
     *                                                 entity of a core source (null for another source). It returns true
     *                                                 when the caller may use the source.
     * @param list<int>|null $storeIds the store views that the caller may see, or null for all store views. With a list,
     *                                 only the core sources run, and they give only the orders of these store views and
     *                                 the customers and the products of their websites.
     * @return list<array{source: string, entity: ?string, entityId: ?int, result: array<string, mixed>}>
     * @throws Mage_Core_Exception_Forbidden when the global search is disabled
     */
    public function search(string $query, ?int $limit, Closure $canSearch, ?array $storeIds = null): array
    {
        if (!$this->isEnabled()) {
            throw new Mage_Core_Exception_Forbidden(
                Mage::helper('adminhtml')->__('The global search is disabled. Enable it in the configuration admin/global_search/enable.'),
            );
        }
        $query = trim($query);
        $sources = Mage::getConfig()->getNode(self::XML_PATH_SOURCES);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH || !$sources) {
            return [];
        }
        $limit = max(1, min($limit ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));

        return Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, function () use ($sources, $query, $limit, $canSearch, $storeIds): array {
            $rows = [];
            foreach ($sources->children() as $name => $config) {
                $className = $config->getClassName();
                if (empty($className)) {
                    continue;
                }
                $entity = $this->coreEntity($className);
                if (($entity === null && $storeIds !== null) || !$canSearch((string) $config->acl, $entity)) {
                    continue;
                }

                $results = new $className()->setStart(1)->setLimit($limit)->setQuery($query)->load()->getResults();
                $sourceRows = [];
                foreach (array_slice(is_array($results) ? array_values($results) : [], 0, $limit) as $result) {
                    $result = is_array($result) ? $result : [];
                    $sourceRows[] = ['source' => (string) $name, ...self::parseId($result['id'] ?? null), 'result' => $result];
                }
                if ($entity !== null && $storeIds !== null) {
                    $sourceRows = $this->filterByStore($sourceRows, $entity, $storeIds);
                }
                array_push($rows, ...$sourceRows);
            }
            return $rows;
        });
    }

    /**
     * Read the entity and its ID from an ID of the form entity/1/123 of the core search sources.
     *
     * @return array{entity: ?string, entityId: ?int}
     */
    private static function parseId(mixed $id): array
    {
        if (is_string($id) && preg_match('#^([a-z][a-z0-9_]*)/\d+/(\d+)$#', $id, $matches)) {
            return ['entity' => $matches[1], 'entityId' => (int) $matches[2]];
        }
        return ['entity' => null, 'entityId' => null];
    }

    private function coreEntity(string $className): ?string
    {
        foreach (self::CORE_SOURCES as $class => $entity) {
            if (is_a($className, $class, true)) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * Keep the orders of $storeIds, and the customers and the products of the websites of $storeIds.
     *
     * @param list<array{source: string, entity: ?string, entityId: ?int, result: array<string, mixed>}> $rows
     * @param list<int> $storeIds
     * @return list<array{source: string, entity: ?string, entityId: ?int, result: array<string, mixed>}>
     */
    private function filterByStore(array $rows, string $entity, array $storeIds): array
    {
        $ids = array_values(array_filter(array_column($rows, 'entityId')));
        $scopeIds = $entity === 'order' ? $storeIds : $this->websiteIds($storeIds);
        if ($ids === [] || $scopeIds === []) {
            return [];
        }

        [$table, $idColumn, $scopeColumn] = match ($entity) {
            'order' => ['sales/order', 'entity_id', 'store_id'],
            'product' => ['catalog/product_website', 'product_id', 'website_id'],
            default => ['customer/entity', 'entity_id', 'website_id'],
        };
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $allowed = array_map(intval(...), $adapter->fetchCol($adapter->select()
            ->from($resource->getTableName($table), [$idColumn])
            ->where("{$idColumn} IN (?)", $ids)
            ->where("{$scopeColumn} IN (?)", $scopeIds)));

        return array_values(array_filter($rows, static fn(array $row): bool => in_array($row['entityId'], $allowed, true)));
    }

    /**
     * Return the websites of $storeIds. A store view that does not exist gives no website.
     *
     * @param list<int> $storeIds
     * @return list<int>
     */
    private function websiteIds(array $storeIds): array
    {
        $websiteIds = [];
        foreach ($storeIds as $storeId) {
            try {
                $websiteIds[] = (int) Mage::app()->getStore($storeId)->getWebsiteId();
            } catch (Mage_Core_Model_Store_Exception) {
            }
        }
        return array_values(array_unique($websiteIds));
    }
}
