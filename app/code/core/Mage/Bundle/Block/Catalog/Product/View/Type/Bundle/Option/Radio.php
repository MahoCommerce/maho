<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

class Mage_Bundle_Block_Catalog_Product_View_Type_Bundle_Option_Radio extends Mage_Bundle_Block_Catalog_Product_View_Type_Bundle_Option
{
    /**
     * Set template
     */
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('bundle/catalog/product/view/type/bundle/option/radio.phtml');
    }
}
