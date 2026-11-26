<?php

/**
 * A request of a customer or a guest to export or to erase the personal data of the store (GDPR).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

namespace Maho\PrivacyCenter\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Maho\ApiPlatform\Metadata\EnumSource;
use Maho\ApiPlatform\Metadata\ListFilter;
use Maho\Config\ApiResource;
use Maho_PrivacyCenter_Model_Request as Request;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/read')",
    mahoLabel: 'Privacy Requests',
    mahoSection: 'Privacy',
    mahoOperations: ['read' => 'View', 'write' => 'Process'],
    mahoCustomerScoped: true,
    shortName: 'PrivacyRequest',
    description: 'GDPR requests to export or to erase the personal data of a customer or a guest. A guest confirms a request with the link of an email. An erasure waits for its cooling-off period, and for the approval of the staff when the store asks for it',
    provider: PrivacyRequestProvider::class,
    processor: PrivacyRequestProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/customers/me/privacy-requests',
            name: 'my_privacy_requests',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'List the privacy requests of the logged-in customer, newest first',
        ),
        new Post(
            uriTemplate: '/customers/me/privacy-requests',
            name: 'submit_my_privacy_request',
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Send a privacy request of the logged-in customer. The request waits for the link of its confirmation email. After the link, an export sends an email with the download link. One open request of each type at a time: a second one answers 409',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['type' => ['type' => 'string', 'enum' => [Request::TYPE_EXPORT, Request::TYPE_ERASURE]]],
                'required' => ['type'],
            ]]]))),
        ),
        new Post(
            uriTemplate: '/customers/me/privacy-requests/{id}/cancel',
            name: 'cancel_my_privacy_request',
            requirements: ['id' => '\d+'],
            read: false,
            status: 200,
            security: "is_granted('ROLE_CUSTOMER')",
            description: 'Cancel an erasure request of the logged-in customer before it runs. It takes no body',
            deserialize: false,
            openapi: new OpenApiOperation(summary: 'Cancels the PrivacyRequest resource.'),
        ),
        new Post(
            uriTemplate: '/privacy-requests',
            name: 'submit_guest_privacy_request',
            security: 'true',
            status: 202,
            description: 'Send the privacy request of a guest. The store emails a confirmation link to the email, so the answer is the same for any email and has no ID. A request for the email of a customer account is a request of that customer',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'type' => ['type' => 'string', 'enum' => [Request::TYPE_EXPORT, Request::TYPE_ERASURE]],
                    'captchaToken' => ['type' => 'string', 'description' => 'CAPTCHA token from the client-side widget, when the store uses a CAPTCHA'],
                ],
                'required' => ['email', 'type'],
            ]]]))),
        ),
        new Post(
            uriTemplate: '/privacy-requests/confirm',
            name: 'confirm_privacy_request',
            security: 'true',
            status: 200,
            description: 'Confirm a privacy request with the token of the link in its confirmation email. A token works once',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['token' => ['type' => 'string']],
                'required' => ['token'],
            ]]]))),
        ),
        new GetCollection(
            uriTemplate: '/privacy-requests',
            security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/read')",
            description: 'List the privacy requests, newest first',
            parameters: [
                'status' => new ListFilter('Only the requests with this status', enum: [
                    Request::STATUS_PENDING, Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING, Request::STATUS_COMPLETED,
                    Request::STATUS_REJECTED, Request::STATUS_CANCELED, Request::STATUS_EXPIRED,
                ]),
                'type' => new ListFilter('Only the requests of this type', enum: [Request::TYPE_EXPORT, Request::TYPE_ERASURE]),
                'email' => new ListFilter('Only the requests of this email'),
                'customerId' => new ListFilter('Only the requests of this customer', 'integer'),
            ],
        ),
        new Get(
            uriTemplate: '/privacy-requests/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/read')",
            description: 'Get a privacy request, with its admin note',
        ),
        new Post(
            uriTemplate: '/privacy-requests/{id}/approve',
            name: 'approve_privacy_request',
            requirements: ['id' => '\d+'],
            read: false,
            status: 200,
            security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/write')",
            description: 'Approve a confirmed erasure request, so that it runs at the end of its cooling-off period. It takes no body',
            deserialize: false,
            openapi: new OpenApiOperation(summary: 'Approves the PrivacyRequest resource.'),
        ),
        new Post(
            uriTemplate: '/privacy-requests/{id}/reject',
            name: 'reject_privacy_request',
            requirements: ['id' => '\d+'],
            read: false,
            status: 200,
            security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/write')",
            description: 'Reject an open privacy request, with an optional reason for the admin note',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['reason' => ['type' => 'string']],
            ]]]))),
        ),
        new Post(
            uriTemplate: '/privacy-requests/{id}/process',
            name: 'process_privacy_request',
            requirements: ['id' => '\d+'],
            read: false,
            status: 200,
            security: "is_granted('ROLE_ADMIN') or is_granted('privacy-requests/write')",
            description: 'Run a confirmed request now: write the export, or erase the data. An erasure waits for its cooling-off period, its approval and the end of the open orders of the customer, unless force is true. An erasure cannot be undone',
            deserialize: false,
            openapi: new OpenApiOperation(requestBody: new RequestBody(content: new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['force' => ['type' => 'boolean', 'default' => false]],
            ]]]))),
        ),
    ],
    graphQlOperations: [],
)]
#[Map(target: Request::class)]
class PrivacyRequest extends \Maho\ApiPlatform\Resource
{
    public const string ADMIN_RESOURCE = 'customer/privacy';
    public const string ACL_VIEW = 'customer/privacy/view';
    public const string ACL_PROCESS = 'customer/privacy/process';

    #[ApiProperty(identifier: true, writable: false, example: 12)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $id = null;

    #[ApiProperty(writable: false, description: 'export: a copy of the personal data. erasure: the deletion of the account and of the personal data', example: 'export', extraProperties: [EnumSource::KEY => 'privacycenter/source_type'])]
    #[Map(if: new TargetClass(self::class))]
    public ?string $type = null;

    #[ApiProperty(writable: false, description: 'pending: waits for the confirmation link. confirmed: an erasure waits for its date and approval. processing, completed, rejected, canceled, expired', example: 'pending', extraProperties: [EnumSource::KEY => 'privacycenter/source_status'])]
    #[Map(if: new TargetClass(self::class))]
    public ?string $status = null;

    #[ApiProperty(writable: false, example: 'jane@example.com')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $email = null;

    #[ApiProperty(writable: false, security: "has_backend_access('privacy-requests')", description: 'The customer of the request. Null for a guest, and after an erasure')]
    #[Map(if: new TargetClass(self::class))]
    public ?int $customerId = null;

    #[ApiProperty(writable: false)]
    #[Map(if: new TargetClass(self::class))]
    public ?int $storeId = null;

    #[ApiProperty(writable: false, description: 'In UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $createdAt = null;

    #[ApiProperty(writable: false, description: 'In UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $confirmedAt = null;

    #[ApiProperty(writable: false, description: 'The date of an erasure, at the end of its cooling-off period, in UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $scheduledAt = null;

    #[ApiProperty(writable: false, description: 'In UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $approvedAt = null;

    #[ApiProperty(writable: false, description: 'In UTC')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $processedAt = null;

    #[ApiProperty(writable: false, security: "has_backend_access('privacy-requests')", description: 'The history of the request: approvals, the result of each eraser, notes of the staff')]
    #[Map(if: new TargetClass(self::class))]
    public ?string $adminNote = null;
}
