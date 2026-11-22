<?php

/**
 * The datasets of the report explorer that the caller may read.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ExplorerDataset',
    description: 'The datasets of the report explorer',
    provider: ExplorerDatasetProvider::class,
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/explore/datasets',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the datasets that the caller may read, each with its metrics (code, label, format), dimensions, filter fields '
                . '(with the values of a field with a short list), scopes, date fields and ready-made reports (presets). '
                . 'Use the codes in the report tool.',
        ),
    ],
    graphQlOperations: [],
)]
class ExplorerDataset extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, description: 'Dataset code')]
    public string $code = '';
}
