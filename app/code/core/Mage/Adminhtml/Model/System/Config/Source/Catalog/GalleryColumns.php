<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Catalog_GalleryColumns
{
    public function toOptionArray(): array
    {
        return array_map(
            fn(int $columns) => ['value' => $columns, 'label' => (string) $columns],
            range(1, Mage_Catalog_Block_Product_View_Media::GALLERY_GRID_COLUMNS_MAX),
        );
    }
}
