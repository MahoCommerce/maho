<?php

/**
 * The body of a new attribute group in a product attribute set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;

final class AttributeSetGroupInput
{
    #[ApiProperty(description: 'Name of the group, unique in the set, at most 255 characters')]
    public ?string $name = null;

    #[ApiProperty(description: 'Position of the group among the groups of the set, 0 or more. Default: after the last group')]
    public ?int $sortOrder = null;
}
