<?php

/**
 * The rows behind the numbers of a dataset: the fields of each row, and where the admin shows one row.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

final class Records
{
    /**
     * @param list<string> $fields the field codes of a row, in the order of the columns
     * @param string $idField the field that identifies a row
     * @param string $aclResource the admin ACL resource that may read the rows, such as sales/order
     * @param string $apiPermission the API permission that may read the rows, such as orders
     * @param string|null $route the admin route that shows one row, such as adminhtml/sales_order/view
     * @param string|null $routeParam the parameter of $route that takes the ID, such as order_id
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $idField,
        public readonly string $aclResource,
        public readonly string $apiPermission,
        public readonly ?string $route = null,
        public readonly ?string $routeParam = null,
    ) {}
}
