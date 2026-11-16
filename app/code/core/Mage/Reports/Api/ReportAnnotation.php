<?php

/**
 * A note on one day of the report charts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\Metadata\ListFilter;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
    shortName: 'ReportAnnotation',
    description: 'Notes on the days of the report charts, such as "newsletter sent" or "site down". The reports also show the start of each active cart price rule',
    provider: ReportAnnotationProvider::class,
    processor: ReportAnnotationProcessor::class,
    denormalizationContext: ['allow_extra_attributes' => false, 'collect_denormalization_errors' => true],
    extraProperties: ['maho_mcp_section' => 'explorer'],
    operations: [
        new GetCollection(
            uriTemplate: '/reports/annotations',
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'List the notes of the administrators, oldest day first',
            parameters: [
                'from' => new ListFilter('Only the notes on this local day or later, YYYY-MM-DD'),
                'to' => new ListFilter('Only the notes on this local day or earlier, YYYY-MM-DD'),
            ],
        ),
        new Get(
            uriTemplate: '/reports/annotations/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('reports/read')",
            description: 'Get a note',
        ),
        new Post(
            uriTemplate: '/reports/annotations',
            read: true,
            security: "is_granted('ROLE_ADMIN')",
            description: 'Add a note on a day. Required: date and title',
        ),
        new Patch(
            uriTemplate: '/reports/annotations/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Change the day or the text of an own note (JSON merge patch)',
        ),
        new Put(
            uriTemplate: '/reports/annotations/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Replace an own note',
        ),
        new Delete(
            uriTemplate: '/reports/annotations/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN')",
            description: 'Delete an own note, or a note whose author was deleted',
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: \Mage_Reports_Model_Annotation::class)]
class ReportAnnotation extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = 'report/explorer';

    #[ApiProperty(identifier: true, writable: false, example: 4)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(description: 'The local day of the note, YYYY-MM-DD', example: '2026-11-10')]
    #[Map(target: 'annotationDate', if: new TargetClass(\Mage_Reports_Model_Annotation::class))]
    #[Map(source: 'annotationDate', if: new TargetClass(self::class))]
    public ?string $date = null;

    #[ApiProperty(description: 'The text of the note, at most 255 characters', example: 'Newsletter sent')]
    #[Map]
    public ?string $title = null;

    #[ApiProperty(writable: false, description: 'The ID of the administrator who wrote the note, null when that administrator was deleted', example: 1)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $adminUserId = null;
}
