<?php

/**
 * The visitor data of the admin dashboard, from the visitor log.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

// The dashboard/read grant of Dashboard gives access, so this uses the plain
// API Platform attribute and is not in the permission registry.
#[ApiResource(
    // The operations that API Platform adds by itself, for example the GraphQL queries, use this expression
    security: "is_granted('ROLE_ADMIN') or is_granted('dashboard/read')",
    shortName: 'DashboardVisitors',
    description: 'Visitor data of the admin dashboard',
    provider: DashboardVisitorsProvider::class,
    operations: [
        new Get(
            uriTemplate: '/dashboard/visitors',
            security: "is_granted('ROLE_ADMIN') or is_granted('dashboard/read')",
            description: 'Get the visitor data of the admin dashboard tabs, from the visitor log. Query: days (1 to 90, default 7), '
                . 'storeId (or websiteId of a website with one store view), sections (comma-separated, default all: summary, trend, devices, '
                . 'engagement, entryPages, exitPages, languages, topPages, trafficSources). enabled is false and there are no sections when '
                . 'the visitor log is off. summary: online (null for one store view, because the online visitors have no store view), today, lastSevenDays, sessions, averageDuration (seconds), averagePages, bounceRate (percent). '
                . 'trend: the visitors of each of the last 30 days (UTC). devices: types (desktop, tablet, mobile) and browsers. '
                . 'engagement: visitors, loggedIn, loginRate (percent), new, returning. entryPages, exitPages and topPages: url with visits, exits or views. '
                . 'languages: total and languages (code, name, visitors). trafficSources: source (referrer host, or direct) and visitors. '
                . 'The days count back from now in UTC, the same as the admin tabs, and the data can be up to one hour old.',
        ),
    ],
    graphQlOperations: [],
)]
class DashboardVisitors extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_DashboardController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Number of days of the breakdowns')]
    public int $days = 7;
}
