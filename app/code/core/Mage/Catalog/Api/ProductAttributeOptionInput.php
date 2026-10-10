<?php

/**
 * The body of a write to an option of a select or multiselect product attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\ApiProperty;

final class ProductAttributeOptionInput
{
    #[ApiProperty(description: 'Admin label of the option, required on create, at most 255 characters')]
    public ?string $label = null;

    #[ApiProperty(description: 'Position of the option in the list, 0 or more')]
    public ?int $sortOrder = null;

    #[ApiProperty(description: 'True makes this option the default value of the attribute. A multiselect attribute can have more than one default option')]
    public ?bool $isDefault = null;

    /** @var array<string, string>|null */
    #[ApiProperty(description: 'Labels by store view code, for example {"default": "Rot"}. An empty label removes the label of that store view', openapiContext: ['type' => 'object', 'additionalProperties' => ['type' => 'string']])]
    public ?array $storeLabels = null;
}
