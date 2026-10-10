<?php

/**
 * One index process of System > Index Management.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

namespace Mage\Index\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\ApiPlatform\Metadata\EnumSource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Index Management',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Change mode and reindex'],
    security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/read')",
    shortName: 'IndexProcess',
    description: 'Index process',
    provider: IndexProcessProvider::class,
    processor: IndexProcessProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/index-processes',
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/read')",
            description: 'List the index processes of System > Index Management with their status (pending, working or require_reindex), '
                . 'mode (real_time updates the index on save, manual waits for a reindex) and updateRequired (true when unprocessed events exist).',
        ),
        new Get(
            uriTemplate: '/index-processes/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/read')",
            description: 'Get one index process by its id.',
        ),
        new Put(
            uriTemplate: '/index-processes/{id}',
            requirements: ['id' => '\d+'],
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/write')",
            description: 'Change the mode of one index process. Body: mode (real_time or manual). Response: the index process after the change.',
        ),
        new Post(
            uriTemplate: '/index-processes/{id}/reindex',
            name: 'index_process_reindex',
            requirements: ['id' => '\d+'],
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/write')",
            description: 'Rebuild one index now and wait until it is done. The call runs the reindex in the request, so it can take minutes on a large catalog. '
                . 'An index that is already working is refused. Response: the index process after the reindex.',
        ),
        new Post(
            uriTemplate: '/index-processes/reindex-all',
            name: 'index_process_reindex_all',
            status: 200,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/write')",
            description: 'Rebuild every index now and wait until all are done. The call runs the reindex in the request, so it can take many minutes on a large catalog. '
                . 'Response: success, reindexed (the number of processes) and processes (the index processes after the reindex).',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an index process by its id',
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List the index processes',
            security: "is_granted('ROLE_ADMIN') or is_granted('index-processes/read')",
        ),
    ],
)]
class IndexProcess extends CrudResource
{
    public const MODEL = 'index/process';

    /** Admin ACL gate. Mirrors backend Mage_Index_Adminhtml_ProcessController. */
    public const ADMIN_RESOURCE = \Mage_Index_Adminhtml_ProcessController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(writable: false, description: 'Code of the indexer, for example catalog_product_price or catalogsearch_fulltext')]
    public string $indexerCode = '';

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Name of the index')]
    public ?string $name = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'What the index holds')]
    public ?string $description = null;

    #[ApiProperty(writable: false, description: 'pending (ready), working (a reindex runs) or require_reindex (the index is out of date)')]
    public ?string $status = null;

    #[ApiProperty(description: 'real_time (update on save) or manual (update only on reindex)', extraProperties: [EnumSource::KEY => ['real_time', 'manual']])]
    public ?string $mode = null;

    #[ApiProperty(writable: false, description: 'Start of the last reindex, UTC')]
    public ?string $startedAt = null;

    #[ApiProperty(writable: false, description: 'End of the last reindex, UTC')]
    public ?string $endedAt = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'True when unprocessed index events wait for this process')]
    public bool $updateRequired = false;

    public static function afterLoad(self $dto, object $model): void
    {
        /** @var \Mage_Index_Model_Process $model */
        $indexer = $model->getIndexer();
        $dto->name = $indexer->getName();
        $dto->description = $indexer->getDescription();
        $dto->updateRequired = $model->getUnprocessedEventsCollection()->count() > 0;
        if ($model->isLocked()) {
            $dto->status = \Mage_Index_Model_Process::STATUS_RUNNING;
        }
    }
}
