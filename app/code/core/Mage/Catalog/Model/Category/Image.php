<?php

/**
 * The source files of the category image entity.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Image implements Mage_Core_Model_Image_ProviderInterface
{
    public const ENTITY = 'category';

    /**
     * The image of every category in every store, for storage:image:resize.
     *
     * @return list<string>
     */
    #[\Override]
    public function getImageFiles(): array
    {
        $attribute = Mage::getResourceSingleton('catalog/category')->getAttribute('image');
        $backend = $attribute ? $attribute->getBackend() : null;
        return $backend instanceof Mage_Eav_Model_Entity_Attribute_Backend_Image ? $backend->getImageFiles() : [];
    }

    #[\Override]
    public function getPlaceholderUrl(int $width): ?string
    {
        return null;
    }
}
