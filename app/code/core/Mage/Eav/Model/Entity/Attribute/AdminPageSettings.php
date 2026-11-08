<?php

/**
 * The settings of the System > Attributes pages for one entity type.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The attribute model of the entity type returns it from getAdminPageSettings().
 * @see Mage_Eav_Model_Entity_Attribute::getAdminPageSettings()
 */
final readonly class Mage_Eav_Model_Entity_Attribute_AdminPageSettings
{
    /**
     * @param string $acl the ACL resource and the menu entry, below system/attributes
     * @param string $title the title of the attribute grid
     * @param string|null $setsTitle the title of the set grid, or null when the type has no sets
     * @param string|null $mainTab the block of the Properties tab of the attribute edit page
     * @param list<string> $allowedTags the HTML tags that an option label keeps when the attribute allows HTML on the storefront
     * @param bool $userDefinedOnly the grid and the set editor list only the attributes that an admin added,
     *                              because the edit form of the entity shows the system attributes with fields of its own
     * @param bool $setsUserDefinedOnly the set editor lists only the attributes that an admin added, because
     *                                  the storefront forms show the system attributes themselves
     * @param bool $addToDefaultSet a new attribute that names no set goes into the first group of the default set,
     *                              whether the admin, an import or code saves it
     * @param bool $singleSet every record uses the default set, so the set pages edit it and cannot add or delete a set
     */
    public function __construct(
        public string $acl,
        public string $title,
        public ?string $setsTitle = null,
        public ?string $mainTab = null,
        public array $allowedTags = [],
        public bool $userDefinedOnly = false,
        public bool $setsUserDefinedOnly = false,
        public bool $addToDefaultSet = false,
        public bool $singleSet = false,
    ) {}

    public function hasSets(): bool
    {
        return $this->setsTitle !== null;
    }
}
