<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Trait;

/**
 * Extracts pagination parameters (page, pageSize) from API Platform context.
 *
 * Handles both REST query parameters ($context['filters']) and GraphQL arguments
 * ($context['args']), normalizing them into a consistent format with configurable
 * defaults and maximum page size clamping.
 */
trait PaginationTrait
{
    /**
     * @return array{page: int, pageSize: int}
     */
    protected function extractPagination(array $context, int $defaultPageSize = 20, int $maxPageSize = 100): array
    {
        $filters = $context['args'] ?? $context['filters'] ?? [];

        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(
            (int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? $defaultPageSize),
            $maxPageSize,
        ));

        return ['page' => $page, 'pageSize' => $pageSize];
    }
}
