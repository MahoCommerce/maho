<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Helper_Map extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_USE_TREE_MODE = 'catalog/sitemap/tree_mode';

    protected $_moduleName = 'Mage_Catalog';

    /**
     * @return string
     */
    public function getCategoryUrl()
    {
        return $this->_getUrl('catalog/seo_sitemap/category');
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        return $this->_getUrl('catalog/seo_sitemap/product');
    }

    /**
     * Return true if category tree mode enabled
     *
     * @return bool
     */
    public function getIsUseCategoryTreeMode()
    {
        return (bool) Mage::getStoreConfigFlag(self::XML_PATH_USE_TREE_MODE);
    }
}
