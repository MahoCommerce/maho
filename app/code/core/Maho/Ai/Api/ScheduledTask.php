<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Scheduled Tasks',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'ScheduledTask',
    description: 'An instruction that the admin assistant runs on a schedule, in a queue worker, with the permissions of its owner: the administrator who saved it last. A run never changes data by itself: a write waits in the run conversation for a confirmation. A run notifies its audience in the admin inbox only when it finds something worth attention, and it reads the answer of the previous run.',
    provider: CrudProvider::class,
    processor: ScheduledTaskProcessor::class,
    operations: [
        new Get(
            uriTemplate: '/scheduled-tasks/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/read')",
            description: 'Get a scheduled task by id',
        ),
        new GetCollection(
            uriTemplate: '/scheduled-tasks',
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/read')",
            description: 'List the scheduled tasks of every administrator',
        ),
        new Post(
            uriTemplate: '/scheduled-tasks',
            processor: ScheduledTaskProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/write')",
            description: 'Create a scheduled task. title, instruction and cronExpr are required. The caller becomes the owner, so only an administrator can create one.',
        ),
        new Put(
            uriTemplate: '/scheduled-tasks/{id}',
            requirements: ['id' => '\d+'],
            processor: ScheduledTaskProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/write')",
            description: 'Update a scheduled task. Only the fields in the request body change, and the caller becomes the owner. Set isActive to false to pause it.',
        ),
        new Delete(
            uriTemplate: '/scheduled-tasks/{id}',
            requirements: ['id' => '\d+'],
            processor: ScheduledTaskProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/delete')",
            description: 'Delete a scheduled task',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a scheduled task by id',
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List scheduled tasks',
            security: "is_granted('ROLE_ADMIN') or is_granted('scheduled-tasks/read')",
        ),
    ],
)]
class ScheduledTask extends CrudResource
{
    public const MODEL = 'ai/task_schedule';
    public const PRIMARY_KEY = 'schedule_id';

    /** Admin ACL gate. Mirrors backend Maho_Ai_Adminhtml_Ai_ScheduleController. */
    public const ADMIN_RESOURCE = \Maho_Ai_Adminhtml_Ai_ScheduleController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'A short name for the task')]
    public ?string $title = null;

    #[ApiProperty(description: 'The complete instruction of one run, as if to a colleague who cannot ask back: what to check, the limits, and when to notify')]
    public ?string $instruction = null;

    #[ApiProperty(description: 'When it runs: five cron fields, minute hour day-of-month month day-of-week, in the store time zone, with one fixed minute. Every day at 8:00 is "0 8 * * *", every Monday at 9:30 is "30 9 * * 1"')]
    public ?string $cronExpr = null;

    #[ApiProperty(description: 'Who sees its notifications: "self" for the owner only, "everyone" for every administrator, or an admin ACL resource the owner has, such as "sales/order", for the administrators allowed it. Default "self"')]
    public ?string $notify = null;

    #[ApiProperty(description: 'False pauses the task')]
    public ?bool $isActive = null;

    #[ApiProperty(writable: false, description: 'The administrator whose permissions the runs use')]
    public ?int $adminUserId = null;

    #[ApiProperty(writable: false, description: 'The next run, in UTC')]
    public ?string $nextRunAt = null;

    #[ApiProperty(writable: false, description: 'The last run, in UTC')]
    public ?string $lastRunAt = null;
}
