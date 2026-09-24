<?php

/**
 * Runs the search sources of the admin global search and returns a raw JSON document.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

namespace Mage\Adminhtml\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class GlobalSearchProvider extends \Maho\ApiPlatform\Provider
{
    public const MIN_QUERY_LENGTH = 2;
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT = 25;

    /**
     * The search sources of the core: class => the entity of their results and the API resource that an API user needs.
     */
    public const CORE_SOURCES = [
        \Mage_Adminhtml_Model_Search_Order::class => ['entity' => 'order', 'resource' => 'orders'],
        \Mage_Adminhtml_Model_Search_Customer::class => ['entity' => 'customer', 'resource' => 'customers'],
        \Mage_Adminhtml_Model_Search_Catalog::class => ['entity' => 'product', 'resource' => 'products'],
    ];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->requireUser();
        if (!\Mage::getStoreConfigFlag('admin/global_search/enable', \Mage_Core_Model_App::ADMIN_STORE_ID)) {
            throw new AccessDeniedHttpException('The global search is disabled. Enable it in the configuration admin/global_search/enable.');
        }

        $filters = ($context['filters'] ?? []) + ($context['request']?->query->all() ?? []);
        $query = trim((string) $this->stringFilter($filters, 'query'));
        $limit = max(1, min($this->intFilter($filters, 'limit') ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));

        $items = mb_strlen($query) < self::MIN_QUERY_LENGTH
            ? []
            : \Mage::app()->withStore(\Mage_Core_Model_App::ADMIN_STORE_ID, fn(): array => $this->search($query, $limit, $user));

        return $this->respondRaw(['query' => $query, 'items' => $items]);
    }

    /**
     * Run each search source that the user may use, the same as Mage_Adminhtml_IndexController::globalSearchAction().
     *
     * @return list<array<string, mixed>>
     */
    private function search(string $query, int $limit, ApiUser $user): array
    {
        $sources = \Mage::getConfig()->getNode('adminhtml/global_search');
        if (!$sources) {
            return [];
        }

        $items = [];
        foreach ($sources->children() as $name => $config) {
            $className = $config->getClassName();
            if (empty($className)) {
                continue;
            }
            $coreSource = $this->coreSource($className);
            if (!$this->canUseSource($user, (string) $config->acl, $coreSource)) {
                continue;
            }

            $source = new $className();
            $results = $source->setStart(1)
                ->setLimit($limit)
                ->setQuery($query)
                ->load()
                ->getResults();

            $results = array_slice(is_array($results) ? array_values($results) : [], 0, $limit);
            if ($coreSource !== null) {
                $results = $this->filterByStore($results, $coreSource['entity'], $user);
            }
            foreach ($results as $result) {
                $items[] = $this->toItem((string) $name, $result);
            }
        }
        return $items;
    }

    /**
     * @return array{entity: string, resource: string}|null
     */
    private function coreSource(string $className): ?array
    {
        foreach (self::CORE_SOURCES as $class => $source) {
            if (is_a($className, $class, true)) {
                return $source;
            }
        }
        return null;
    }

    /**
     * An admin token uses the ACL of the source. An API user has no admin role, so it uses the core sources
     * with the API permission of their entity. A token with a store restriction uses only the core sources,
     * because only their results have a store or a website that this provider can check.
     *
     * @param array{entity: string, resource: string}|null $coreSource
     */
    private function canUseSource(ApiUser $user, string $acl, ?array $coreSource): bool
    {
        if ($coreSource === null && $user->getAllowedStoreIds() !== null) {
            return false;
        }
        if ($user->isAdmin()) {
            return $acl === '' || \Mage::getSingleton('admin/session')->isAllowed($acl);
        }
        return $coreSource !== null && $this->hasBackendAccess($coreSource['resource']);
    }

    /**
     * Remove the orders of other stores and the customers of other websites for a token with a store restriction.
     * The products are global.
     *
     * @param list<mixed> $results
     * @return list<mixed>
     */
    private function filterByStore(array $results, string $entity, ApiUser $user): array
    {
        if ($user->getAllowedStoreIds() === null || $entity === 'product') {
            return $results;
        }

        $ids = [];
        foreach ($results as $result) {
            $id = self::parseId(is_array($result) ? ($result['id'] ?? null) : null)['entityId'];
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $allowed = $this->allowedEntityIds($entity, $ids, $user);

        return array_values(array_filter($results, static function (mixed $result) use ($allowed): bool {
            $id = self::parseId(is_array($result) ? ($result['id'] ?? null) : null)['entityId'];
            return $id !== null && in_array($id, $allowed, true);
        }));
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function allowedEntityIds(string $entity, array $ids, ApiUser $user): array
    {
        if ($ids === []) {
            return [];
        }
        if ($entity === 'order') {
            $table = 'sales/order';
            $column = 'store_id';
            $scopeIds = $user->getAllowedStoreIds() ?? [];
        } else {
            $table = 'customer/entity';
            $column = 'website_id';
            $scopeIds = $this->allowedWebsiteIds($user) ?? [];
        }
        if ($scopeIds === []) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName($table), ['entity_id'])
            ->where('entity_id IN (?)', $ids)
            ->where("{$column} IN (?)", $scopeIds);
        return array_map(intval(...), $adapter->fetchCol($select));
    }

    /**
     * @return array<string, mixed>
     */
    private function toItem(string $source, mixed $result): array
    {
        $result = is_array($result) ? $result : [];
        return [
            'source' => $source,
            'type' => self::text($result['type'] ?? null),
            ...self::parseId($result['id'] ?? null),
            'name' => self::text($result['name'] ?? null),
            'description' => self::text($result['description'] ?? null),
        ];
    }

    /**
     * Read the entity and its id from an id of the form entity/1/123 of the core search sources.
     *
     * @return array{entity: ?string, entityId: ?int}
     */
    public static function parseId(mixed $id): array
    {
        if (is_string($id) && preg_match('#^([a-z][a-z0-9_]*)/\d+/(\d+)$#', $id, $matches)) {
            return ['entity' => $matches[1], 'entityId' => (int) $matches[2]];
        }
        return ['entity' => null, 'entityId' => null];
    }

    /**
     * Return a text of a search result without its HTML, or null when it is empty.
     */
    public static function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string) $value;
        $stripped = strip_tags($value);
        if ($stripped !== $value) {
            $value = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
