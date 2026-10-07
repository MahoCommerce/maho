<?php

/**
 * One system configuration field (System > Configuration) at one scope.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use Maho\ApiPlatform\Metadata\EnumSource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'System Configuration',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View', 'write' => 'Change', 'delete' => 'Restore inheritance'],
    security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/read')",
    shortName: 'ConfigSetting',
    description: 'System configuration setting',
    provider: ConfigSettingProvider::class,
    processor: ConfigSettingProcessor::class,
    operations: [
        new GetCollection(
            uriTemplate: '/config-settings',
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/read')",
            description: 'List the system configuration settings (the fields of System > Configuration) with their value at one scope. '
                . 'Query: scope (default, websites or stores; default is default), scopeCode (the website or store view code, required for websites and stores), '
                . 'pathPrefix (for example general/ or catalog/frontend), search (partial match on the path, the label or the help text). '
                . 'Each item has path (section/group/field), value, inherited (true when the scope has no own value and uses the parent scope), '
                . 'isSensitive (true when the value is a secret; the value is then null). An admin token sees only the sections that its admin role allows.',
        ),
        new Get(
            uriTemplate: '/config-settings/{path}',
            requirements: ['path' => '.+'],
            uriVariables: ['path' => new Link(fromClass: self::class, identifiers: ['path'])],
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/read')",
            description: 'Get one system configuration setting by its path, for example general/store_information/name. '
                . 'Query: scope (default, websites or stores; default is default), scopeCode (the website or store view code). '
                . 'The value of a sensitive field (API key, password, token) is null and isSensitive is true.',
        ),
        new Put(
            uriTemplate: '/config-settings/{path}',
            requirements: ['path' => '.+'],
            uriVariables: ['path' => new Link(fromClass: self::class, identifiers: ['path'])],
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/write')",
            description: 'Set the value of one system configuration setting at one scope. Body: value (string; a boolean becomes 1 or 0; a list becomes a comma-separated string), '
                . 'scope (default, websites or stores; default is default), scopeCode (the website or store view code, required for websites and stores). '
                . 'The field must exist in System > Configuration and must allow the scope. The write uses the backend model of the field, so a secret is encrypted. '
                . 'Response: the setting after the write. The value of a sensitive field stays null in the response.',
        ),
        new Delete(
            uriTemplate: '/config-settings/{path}',
            requirements: ['path' => '.+'],
            uriVariables: ['path' => new Link(fromClass: self::class, identifiers: ['path'])],
            read: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/delete')",
            description: 'Remove the own value of one system configuration setting at a website or store view scope, so the setting inherits the value of the parent scope again. '
                . 'Query: scope (websites or stores, required), scopeCode (the website or store view code, required). The default scope has no parent, so it cannot be deleted.',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get a system configuration setting by its path',
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List the system configuration settings',
            security: "is_granted('ROLE_ADMIN') or is_granted('config-settings/read')",
            extraArgs: [
                'pathPrefix' => ['type' => 'String', 'description' => 'Only the settings whose path starts with this prefix, for example general/ or catalog/frontend'],
                'scope' => ['type' => 'String', 'description' => 'Scope of the values: default, websites or stores (default is default)'],
                'scopeCode' => ['type' => 'String', 'description' => 'Website code for scope websites, store view code for scope stores'],
                'search' => ['type' => 'String', 'description' => 'Partial match on the path, the label or the help text of the setting'],
            ],
        ),
    ],
)]
class ConfigSetting extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Mirrors backend Mage_Adminhtml_System_ConfigController. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_System_ConfigController::ADMIN_RESOURCE;

    public const SCOPE_DEFAULT = 'default';
    public const SCOPE_WEBSITES = 'websites';
    public const SCOPE_STORES = 'stores';

    #[ApiProperty(identifier: true, writable: false, description: 'Configuration path: section/group/field, for example general/store_information/name')]
    public string $path = '';

    #[ApiProperty(description: 'Value at the requested scope. Null when the field is sensitive or has no value')]
    public ?string $value = null;

    #[ApiProperty(description: 'Scope of the value: default, websites or stores', extraProperties: [EnumSource::KEY => ['default', 'websites', 'stores']])]
    public string $scope = self::SCOPE_DEFAULT;

    #[ApiProperty(description: 'Website code for scope websites, store view code for scope stores, null for scope default')]
    public ?string $scopeCode = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'True when the scope has no own value and uses the value of the parent scope')]
    public bool $inherited = false;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'True when the value is a secret (API key, password, token). The value is then null')]
    public bool $isSensitive = false;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Label of the field in System > Configuration')]
    public ?string $label = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Label of the group that holds the field')]
    public ?string $groupLabel = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Label of the section that holds the field')]
    public ?string $sectionLabel = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Help text shown under the field in System > Configuration, or null; the search filter matches it')]
    public ?string $comment = null;

    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'Input type of the field: text, select, multiselect, textarea, obscure and others')]
    public ?string $frontendType = null;

    /**
     * @var list<array{value: string, label: string}>|null
     */
    #[ApiProperty(writable: false, extraProperties: ['computed' => true], description: 'The values that a select, multiselect or boolean field takes, each with its label. Only on one setting, never in a list. Null for other fields and for a field with more than 50 values')]
    public ?array $options = null;
}
