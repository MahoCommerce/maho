<?php

/**
 * The permissions of the reports for API users: read gives all reports, refresh updates their statistics.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\Config\ApiResource;

// Each report is a resource of its own with the admin ACL of the report,
// and all reports share these two permissions of API users.
#[ApiResource(
    mahoId: 'reports',
    mahoLabel: 'Reports',
    mahoSection: 'Reports',
    mahoOperations: ['read' => 'View', 'refresh' => 'Refresh statistics'],
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ReportsPermission',
    description: 'Permissions of the reports',
    operations: [],
    graphQlOperations: [],
)]
class ReportsPermission extends \Maho\ApiPlatform\Resource {}
