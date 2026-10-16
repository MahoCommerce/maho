<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Attribute_Backend_Image extends Mage_Eav_Model_Entity_Attribute_Backend_Image
{
    /** Directory of category images on the media mount. */
    public const STORAGE_PATH = 'catalog/category';

    #[\Override]
    public function getImageEntity(): string
    {
        return Mage_Catalog_Model_Category_Image::ENTITY;
    }
}
