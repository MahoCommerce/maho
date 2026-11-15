<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * A table of the attributes of a category that have "Visible on Front" set.
 */
class Mage_Catalog_Block_Category_Attributes extends Mage_Eav_Block_Frontend_Attributes
{
    #[\Override]
    public function getValueHtml(array $row): string
    {
        $category = $this->getEntity();
        if (!$category instanceof Mage_Catalog_Model_Category) {
            return parent::getValueHtml($row);
        }
        return (string) $this->helper('catalog/output')->categoryAttribute($category, $row['value'], $row['code']);
    }
}
