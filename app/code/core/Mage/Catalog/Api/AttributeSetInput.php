<?php

/**
 * The body of a create or rename of a product attribute set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;

final class AttributeSetInput
{
    #[ApiProperty(description: 'Name of the attribute set, unique among the sets of products, at most 255 characters')]
    public ?string $name = null;

    #[ApiProperty(description: 'On create only: the ID of the set whose groups and attributes the new set copies. Default: the default set of products')]
    public ?int $skeletonId = null;
}
