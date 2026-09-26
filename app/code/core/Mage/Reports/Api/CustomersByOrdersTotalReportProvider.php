<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class CustomersByOrdersTotalReportProvider extends ReportProviderBase
{
    use CustomersRankingTrait;

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        return $this->customersRanking(
            $filters,
            $user,
            'customers-by-orders-total',
            static fn(\Mage_Reports_Model_Resource_Order_Collection $collection) => $collection->orderByTotalAmount(),
        );
    }
}
