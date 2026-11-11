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

final class GlobalSearchProvider extends \Maho\ApiPlatform\Provider
{
    public const MIN_QUERY_LENGTH = \Mage_Adminhtml_Service_GlobalSearch::MIN_QUERY_LENGTH;
    public const DEFAULT_LIMIT = \Mage_Adminhtml_Service_GlobalSearch::DEFAULT_LIMIT;
    public const MAX_LIMIT = \Mage_Adminhtml_Service_GlobalSearch::MAX_LIMIT;

    /**
     * The API resource that an API user needs for the results of each core search source, by entity.
     */
    private const API_RESOURCES = ['order' => 'orders', 'customer' => 'customers', 'product' => 'products'];

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->requireUser();
        $filters = ($context['filters'] ?? []) + ($context['request']?->query->all() ?? []);
        $query = trim((string) $this->stringFilter($filters, 'query'));

        $rows = \Mage::getService('adminhtml/globalSearch')->search(
            $query,
            $this->intFilter($filters, 'limit'),
            fn(string $acl, ?string $entity): bool => $this->canUseSource($user, $acl, $entity),
            $user->getAllowedStoreIds(),
        );

        return $this->respondRaw(['query' => $query, 'items' => array_map($this->toItem(...), $rows)]);
    }

    /**
     * An admin token uses the ACL of the source. An API user has no admin role, so it uses the core sources
     * with the API permission of their entity.
     */
    private function canUseSource(ApiUser $user, string $acl, ?string $entity): bool
    {
        if ($user->isAdmin()) {
            return $acl === '' || \Mage::getSingleton('admin/session')->isAllowed($acl);
        }
        return $entity !== null && $this->hasBackendAccess(self::API_RESOURCES[$entity]);
    }

    /**
     * @param array{source: string, entity: ?string, entityId: ?int, result: array<string, mixed>} $row
     * @return array<string, mixed>
     */
    private function toItem(array $row): array
    {
        return [
            'source' => $row['source'],
            'type' => self::text($row['result']['type'] ?? null),
            'entity' => $row['entity'],
            'entityId' => $row['entityId'],
            'name' => self::text($row['result']['name'] ?? null),
            'description' => self::text($row['result']['description'] ?? null),
        ];
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
