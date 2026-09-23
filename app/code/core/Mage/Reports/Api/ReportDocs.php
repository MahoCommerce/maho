<?php

/**
 * Texts that the OpenAPI descriptions of the reports share.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

final class ReportDocs
{
    public const PERIOD_QUERY = 'Query: from and to (YYYY-MM-DD, required, inclusive, in the time zone of the default scope), '
        . 'periodType (day, month or year, default day, at most 1000 periods), storeId or websiteId (not both), '
        . 'emptyPeriods (default true: the response has each period of the range, also the periods without data),';

    public const ENVELOPE = 'Response: report, currency (the global base currency of all amounts), timezone, periodType, from, to, '
        . 'dateBasis, scope, statistics (code and updatedAt, the last refresh of the statistics), totals and periods. '
        . 'Period labels are 2026-09-01 for day, 2026-09 for month and 2026 for year.';

    public const SCOPE_QUERY = 'storeId or websiteId (not both)';
}
