<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoLabel: 'Themes',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    shortName: 'Theme',
    description: 'Storefront theme installed on disk, identified as "package/theme". Read-only: themes are files, not database rows. Use the id as the design value of a design change.',
    provider: ThemeProvider::class,
    operations: [
        new GetCollection(
            uriTemplate: '/themes',
            security: "is_granted('ROLE_ADMIN') or is_granted('themes/read')",
            description: 'List every installed storefront theme',
        ),
        new Get(
            uriTemplate: '/themes/{id}',
            requirements: ['id' => '[^/]+/[^/]+'],
            security: "is_granted('ROLE_ADMIN') or is_granted('themes/read')",
            description: 'Get one installed theme by its "package/theme" id',
        ),
    ],
    graphQlOperations: [
        new Query(
            name: 'item_query',
            description: 'Get an installed theme by its "package/theme" id',
            security: "is_granted('ROLE_ADMIN') or is_granted('themes/read')",
        ),
        new QueryCollection(
            name: 'collection_query',
            description: 'List installed storefront themes',
            security: "is_granted('ROLE_ADMIN') or is_granted('themes/read')",
        ),
    ],
)]
class Theme extends \Maho\ApiPlatform\Resource
{
    /** Admin ACL gate. Themes are managed from the design schedule in the backend. */
    public const ADMIN_RESOURCE = \Mage_Adminhtml_System_DesignController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Theme id as "package/theme"')]
    public string $id = '';

    #[ApiProperty(description: 'Design package (the folder under app/design/frontend)')]
    public string $package = '';

    #[ApiProperty(description: 'Theme name (the folder under the package)')]
    public string $theme = '';

    #[ApiProperty(description: 'Area the theme renders. Always "frontend".')]
    public string $area = 'frontend';

    #[ApiProperty(description: 'True when this theme is the default-scope storefront theme')]
    public bool $isDefault = false;

    /**
     * Every installed "package/theme" id, sorted.
     *
     * @return list<string>
     */
    public static function installedIds(): array
    {
        $ids = [];
        foreach (\Mage::getModel('core/design_package')->getThemeList() as $package => $themes) {
            foreach ((array) $themes as $theme) {
                $ids[] = $package . '/' . $theme;
            }
        }
        sort($ids);

        return $ids;
    }

    public static function defaultId(): string
    {
        $adminStore = \Mage_Core_Model_App::ADMIN_STORE_ID;
        $package = (string) (\Mage::getStoreConfig('design/package/name', $adminStore) ?: 'base');
        $theme = (string) (\Mage::getStoreConfig('design/theme/default', $adminStore) ?: 'default');

        return $package . '/' . $theme;
    }
}
