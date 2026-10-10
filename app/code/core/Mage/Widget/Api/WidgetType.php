<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

namespace Mage\Widget\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Widget Types',
    mahoSection: 'Content',
    mahoOperations: ['read' => 'View'],
    shortName: 'WidgetType',
    description: 'Widget a CMS page, block or email template can embed with a {{widget}} directive: product lists, new products, bestsellers, links, blog posts. Read-only: widget types come from module configuration. Prefer a widget directive over handwritten HTML for dynamic content.',
    provider: WidgetTypeProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/widget-types',
            security: "is_granted('ROLE_ADMIN') or is_granted('widget-types/read')",
            description: 'List every widget type with an example directive. The parameters come with the single item.',
        ),
        new Get(
            uriTemplate: '/widget-types/{id}',
            security: "is_granted('ROLE_ADMIN') or is_granted('widget-types/read')",
            description: 'Get one widget type by its code, with every parameter',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a widget type by its code',
            security: "is_granted('ROLE_ADMIN') or is_granted('widget-types/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List widget types',
            security: "is_granted('ROLE_ADMIN') or is_granted('widget-types/read')",
        ),
    ],
)]
class WidgetType extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate: the same as the widget instances the backend manages. */
    public const ADMIN_RESOURCE = \Mage_Widget_Adminhtml_Widget_InstanceController::ADMIN_RESOURCE;

    /** A widget type has no admin page: the page of its ACL resource edits widget instances. */
    public const ADMIN_RECORD_PAGE = false;

    #[ApiProperty(identifier: true, description: 'Widget code, for example "new_products"')]
    public string $id = '';

    #[ApiProperty(description: 'Block type the directive names, for example "catalog/product_widget_new"')]
    public string $type = '';

    #[ApiProperty(description: 'Widget name')]
    public string $name = '';

    #[ApiProperty(description: 'What the widget renders')]
    public string $description = '';

    #[ApiProperty(description: 'True when the widget can also render inside an email template')]
    public bool $isEmailCompatible = false;

    /**
     * @var list<array{name: string, label: string, type: string, required: bool, default: string|null, options: list<array{value: string, label: string}>, description: string}>
     */
    #[ApiProperty(description: 'Parameters the directive accepts: name, label, type, required, default, options and description. Empty in the list, filled in the single item.')]
    public array $parameters = [];

    #[ApiProperty(description: 'Directive with the default values, ready to paste into page content')]
    public string $directive = '';
}
