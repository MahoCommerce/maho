<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Block_Product_Widget_Link extends Mage_Catalog_Block_Widget_Link
{
    /**
     * Initialize entity model
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_entityResource = Mage::getResourceSingleton('catalog/product');
    }
}
