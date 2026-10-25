<?php

/**
 * The version and the build of the code that runs the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use Maho\Config\ApiResource;

#[ApiResource(
    mahoId: 'build-info',
    mahoLabel: 'Build Information',
    mahoSection: 'System',
    mahoOperations: ['read' => 'View'],
    security: "is_granted('ROLE_ADMIN') or is_granted('build-info/read')",
    shortName: 'BuildInfo',
    description: 'Version and build of the installation',
    provider: BuildInfoProvider::class,
    operations: [
        new Get(
            uriTemplate: '/build-info',
            security: "is_granted('ROLE_ADMIN') or is_granted('build-info/read')",
            description: 'Get the version and the build of the installation. version is the version of the next release, so two builds can have the same version. '
                . 'build is the short commit of the code on disk, or unknown when the installation keeps no trace of it.',
        ),
    ],
    graphQlOperations: [],
)]
class BuildInfo extends \Maho\ApiPlatform\Resource
{
    public const ADMIN_RESOURCE = \Mage_Adminhtml_System_Tools_HealthcheckController::ADMIN_RESOURCE;

    #[ApiProperty(identifier: true, description: 'Version of the installation')]
    public string $version = '';

    #[ApiProperty(description: 'Short commit of the code on disk, or unknown')]
    public string $build = '';
}
