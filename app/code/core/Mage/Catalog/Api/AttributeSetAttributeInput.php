<?php

/**
 * The body of an assignment of a product attribute to a group of an attribute set.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;

final class AttributeSetAttributeInput
{
    #[ApiProperty(description: 'ID of the product attribute. Give attributeId or attributeCode')]
    public ?int $attributeId = null;

    #[ApiProperty(description: 'Code of the product attribute. Give attributeId or attributeCode')]
    public ?string $attributeCode = null;

    #[ApiProperty(description: 'ID of a group of the set. Give groupId or groupName')]
    public ?int $groupId = null;

    #[ApiProperty(description: 'Name of a group of the set. Give groupId or groupName')]
    public ?string $groupName = null;

    #[ApiProperty(description: 'Position of the attribute in the group, 0 or more. Default: after the last attribute of the group')]
    public ?int $sortOrder = null;
}
