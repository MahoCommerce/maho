<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

namespace Maho\Revocation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\Resource;
use Maho\Config\ApiResource;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[ApiResource(
    mahoSection: 'Sales',
    mahoOperations: ['read' => 'View', 'write' => 'Submit & Process'],
    mahoCustomerScoped: true,
    shortName: 'RevocationRequest',
    description: 'Submit a contract revocation (EU Directive 2023/2673) against your own order, view your declarations; admins list and process requests',
    provider: RevocationRequestProvider::class,
    processor: RevocationRequestProcessor::class,
    operations: [
        new Post(
            uriTemplate: '/customers/me/revocation-requests',
            name: 'submit_revocation',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('revocation-requests/write')",
            description: 'Submit a verified revocation declaration against one of your own orders',
        ),
        new GetCollection(
            uriTemplate: '/customers/me/revocation-requests',
            name: 'my_revocation_requests',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('revocation-requests/read')",
            description: 'List the current customer revocation declarations',
        ),
        new Get(
            uriTemplate: '/revocation-requests/{id}',
            // Row-level: ownership is the email on the declaration (guest
            // submissions have no customer id). Denial maps to 404 so a foreign
            // id is indistinguishable from a missing one.
            security: "is_back_office('revocation-requests') or is_owner(object, 'email')",
            exceptionToStatus: [AccessDeniedException::class => 404],
            requirements: ['id' => '\d+'],
            description: 'Get a single revocation request (own request for customers, any for admins)',
        ),
        new GetCollection(
            uriTemplate: '/revocation-requests',
            name: 'admin_revocation_requests',
            security: "is_granted('ROLE_ADMIN') or is_granted('revocation-requests/read')",
            description: 'List all revocation requests (admin only)',
        ),
        new Put(
            uriTemplate: '/revocation-requests/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('revocation-requests/write')",
            requirements: ['id' => '\d+'],
            description: 'Update the processing status and internal note of a revocation request (admin only)',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a revocation request by ID',
            // Row-level denial is converted to a null result by
            // OwnershipDenialProvider.
            security: "is_back_office('revocation-requests') or is_owner(object, 'email')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List revocation requests (admin only)',
            security: "is_granted('ROLE_ADMIN') or is_granted('revocation-requests/read')",
            extraArgs: [
                'email' => ['type' => 'String', 'description' => 'Exact email match'],
                'orderId' => ['type' => 'Int', 'description' => 'Filter by order ID'],
                'processedStatus' => ['type' => 'String', 'description' => 'Filter by processing status'],
                'storeId' => ['type' => 'Int', 'description' => 'Filter by store ID'],
            ],
        ),
        new QueryCollection(
            // Named 'my' (not 'myRevocationRequests') so ApiPlatform's appended
            // resource shortName yields the field `myRevocationRequests` rather
            // than a stuttering `myRevocationRequestsRevocationRequests`.
            name: 'my',
            description: 'List the current customer revocation declarations',
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_ADMIN') or is_granted('revocation-requests/read')",
        ),
        new Mutation(
            name: 'submit',
            args: [
                'orderId' => ['type' => 'Int', 'description' => 'Entity ID of one of your own orders'],
                'orderReference' => ['type' => 'String', 'description' => 'Order increment ID (alternative to orderId)'],
                'reason' => ['type' => 'String', 'description' => 'Optional reason for the revocation'],
            ],
            security: "is_granted('ROLE_CUSTOMER') or is_granted('revocation-requests/write')",
            description: 'Submit a verified revocation declaration against one of your own orders',
        ),
    ],
)]
class RevocationRequest extends Resource
{
    /** Admin ACL gate. Mirrors Maho_Revocation_Adminhtml_Sales_RevocationController. */
    public const ADMIN_RESOURCE = 'sales/revocation';

    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[ApiProperty(description: 'Entity ID of the order being revoked (write: the order to revoke)', writable: true)]
    public ?int $orderId = null;

    #[ApiProperty(description: 'Order increment ID as recorded on the declaration', writable: true)]
    public ?string $orderReference = null;

    #[ApiProperty(description: 'Optional reason for the revocation', writable: true)]
    public ?string $reason = null;

    #[ApiProperty(description: 'Name on the declaration', writable: false)]
    public ?string $customerName = null;

    #[ApiProperty(description: 'Email on the declaration', writable: false)]
    public ?string $email = null;

    #[ApiProperty(description: 'Whether the declaration was submitted from an authenticated, order-owning session', writable: false)]
    public bool $verified = false;

    #[ApiProperty(description: 'Store ID the declaration was submitted on', writable: false)]
    public ?int $storeId = null;

    #[ApiProperty(description: 'When the declaration was received (UTC)', writable: false)]
    public ?string $receivedAt = null;

    #[ApiProperty(description: 'Processing status: accepted, rejected, info_requested, or null when unprocessed', writable: true)]
    public ?string $processedStatus = null;

    #[ApiProperty(description: 'When the declaration was processed (UTC)', writable: false)]
    public ?string $processedAt = null;

    #[ApiProperty(description: 'Internal admin note (admin only)', writable: true, security: "is_back_office('revocation-requests')")]
    public ?string $adminNote = null;

    #[ApiProperty(description: 'IP address the declaration was submitted from (admin only)', writable: false, security: "is_back_office('revocation-requests')")]
    public ?string $ip = null;

    #[ApiProperty(description: 'User agent the declaration was submitted from (admin only)', writable: false, security: "is_back_office('revocation-requests')")]
    public ?string $userAgent = null;

    #[ApiProperty(description: 'When the customer receipt email was suppressed (UTC), if any', writable: false)]
    public ?string $suppressedAt = null;

    #[ApiProperty(description: 'Reason the customer receipt email was suppressed, if any', writable: false)]
    public ?string $suppressedReason = null;

    public static function fromModel(object $model): self
    {
        $dto = new self();
        $dto->id = (int) $model->getId();
        $dto->orderId = $model->getOrderId();
        $dto->orderReference = $model->getOrderReference();
        $dto->reason = $model->getReason();
        $dto->customerName = $model->getCustomerName();
        $dto->email = $model->getEmail();
        $dto->verified = (bool) $model->getVerified();
        $dto->storeId = $model->getStoreId();
        $dto->receivedAt = $model->getReceivedAt() ?: null;
        $dto->processedStatus = $model->getProcessedStatus();
        $dto->processedAt = $model->getProcessedAt();
        $dto->suppressedAt = $model->getSuppressedAt();
        $dto->suppressedReason = $model->getSuppressedReason();
        $dto->adminNote = $model->getAdminNote();
        $dto->ip = $model->getIp();
        $dto->userAgent = $model->getUserAgent();

        return $dto;
    }
}
