<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudResource;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Email Templates',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Create & Update', 'delete' => 'Delete'],
    shortName: 'EmailTemplate',
    description: 'Transactional email template stored in the database. A template holds the subject and body sent for an event such as a new order or a password reset. Template text may contain {{var ...}}, {{depend ...}} and other template directives; they are kept verbatim.',
    provider: EmailTemplateProvider::class,
    processor: EmailTemplateProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/email-templates/defaults',
            name: 'list_default_email_templates',
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
            description: 'List the built-in default email templates shipped with Maho. These are not stored in the database and have no id. Use get_default_email_template to read the full text of one, then create a database template from it with origTemplateCode set to the default code.',
        ),
        new Get(
            uriTemplate: '/email-templates/defaults/{code}',
            name: 'get_default_email_template',
            requirements: ['code' => '[a-z0-9_]+'],
            // String-typed Link so the converter passes the code through to the provider.
            uriVariables: ['code' => new Link(fromClass: self::class, identifiers: ['templateCode'])],
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
            description: 'Get one built-in default email template by its code (for example sales_email_order_template). Returns the subject, text, styles and variables of the file-based default.',
        ),
        new Get(
            uriTemplate: '/email-templates/{id}',
            requirements: ['id' => '\d+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
            description: 'Get a database email template by id',
        ),
        new GetCollection(
            uriTemplate: '/email-templates',
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
            description: 'List the email templates stored in the database',
        ),
        new Post(
            uriTemplate: '/email-templates',
            processor: EmailTemplateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/write')",
            description: 'Create an email template. templateCode must be unique and templateText is required. templateType defaults to html.',
        ),
        new Put(
            uriTemplate: '/email-templates/{id}',
            requirements: ['id' => '\d+'],
            processor: EmailTemplateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/write')",
            description: 'Update an email template. Only the fields in the request body change.',
        ),
        new Delete(
            uriTemplate: '/email-templates/{id}',
            requirements: ['id' => '\d+'],
            processor: EmailTemplateProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/delete')",
            description: 'Delete an email template',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an email template by id',
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List email templates',
            security: "is_granted('ROLE_ADMIN') or is_granted('email-templates/read')",
            extraArgs: [
                'search' => ['type' => 'String', 'description' => 'Partial match on the template code or subject'],
                'templateType' => ['type' => 'String', 'description' => 'Only templates of this type: text or html'],
            ],
        ),
    ],
)]
class EmailTemplate extends CrudResource
{
    public const MODEL = 'core/email_template';
    public const PRIMARY_KEY = 'template_id';

    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_System_Email_TemplateController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_System_Email_TemplateController::ADMIN_RESOURCE;

    public const TYPE_TEXT = 'text';
    public const TYPE_HTML = 'html';

    #[ApiProperty(identifier: true, writable: false, description: 'Database id. Null for a built-in default template.')]
    public ?int $id = null;

    #[ApiProperty(description: 'Unique template name shown in the admin, for example "New order (EN)"')]
    public ?string $templateCode = null;

    #[ApiProperty(description: 'Email subject. May contain {{var ...}} directives.')]
    public ?string $templateSubject = null;

    #[ApiProperty(description: 'Email body. HTML for an html template, plain text for a text template. Template directives are kept verbatim.')]
    public ?string $templateText = null;

    #[ApiProperty(description: 'CSS added to an html template')]
    public ?string $templateStyles = null;

    #[ApiProperty(description: 'Body format: "text" or "html"', extraProperties: ['computed' => true])]
    public ?string $templateType = null;

    #[ApiProperty(description: 'Sender name override. Null uses the store contact.')]
    public ?string $templateSenderName = null;

    #[ApiProperty(description: 'Sender email override. Null uses the store contact.')]
    public ?string $templateSenderEmail = null;

    #[ApiProperty(description: 'Code of the built-in default template this one was created from')]
    public ?string $origTemplateCode = null;

    #[ApiProperty(description: 'Variables the template may use, as the admin variable picker stores them')]
    public ?string $origTemplateVariables = null;

    #[ApiProperty(writable: false, description: 'Creation time (UTC)')]
    public ?string $addedAt = null;

    #[ApiProperty(writable: false, description: 'Last modification time (UTC)')]
    public ?string $modifiedAt = null;

    public static function afterLoad(self $dto, object $model): void
    {
        $dto->templateType = self::typeToString($model->getData('template_type'));
    }

    public static function typeToString(mixed $type): string
    {
        return (int) $type === \Mage_Core_Model_Email_Template::TYPE_TEXT ? self::TYPE_TEXT : self::TYPE_HTML;
    }

    public static function typeToInt(string $type): int
    {
        return $type === self::TYPE_TEXT
            ? \Mage_Core_Model_Email_Template::TYPE_TEXT
            : \Mage_Core_Model_Email_Template::TYPE_HTML;
    }
}
