<?php

/**
 * Source of the product attribute visibility.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Attribute_Source_Visibility extends Mage_Catalog_Model_Product_Attribute_Source_Abstract
{
    #[\Override]
    public function getOptionArray(): array
    {
        return Mage_Catalog_Model_Product_Visibility::getOptionArray();
    }
}
