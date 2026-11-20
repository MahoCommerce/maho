<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Attribute_Source_Placement extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        if (!$this->_options) {
            $this->_options = [
                [
                    'value' => Mage_Catalog_Model_Category::PLACEMENT_TOP,
                    'label' => Mage::helper('catalog')->__('Top of the list'),
                ],
                [
                    'value' => Mage_Catalog_Model_Category::PLACEMENT_BOTTOM,
                    'label' => Mage::helper('catalog')->__('Bottom of the list'),
                ],
            ];
        }
        return $this->_options;
    }
}
