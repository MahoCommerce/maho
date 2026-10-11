<?php

/**
 * The source files of the category image entity.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Image
{
    public const ENTITY = 'category';

    /**
     * The image of every category in every store, for image:resize.
     *
     * @return list<string>
     */
    public function getImageFiles(): array
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'image');
        if (!$attribute || !$attribute->getId()) {
            return [];
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $files = $adapter->fetchCol($adapter->select()
            ->distinct()
            ->from($attribute->getBackend()->getTable(), ['value'])
            ->where('attribute_id = ?', (int) $attribute->getId()));

        return array_values(array_filter(array_map(strval(...), $files), fn(string $file): bool => $file !== ''));
    }
}
