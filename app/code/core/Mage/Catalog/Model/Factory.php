<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Factory extends Mage_Core_Model_Factory
{
    /**
     * Xml path to the category url rewrite helper class alias
     */
    public const XML_PATH_CATEGORY_URL_REWRITE_HELPER_CLASS = 'global/catalog/category/url_rewrite/helper';

    /**
     * Xml path to the product url rewrite helper class alias
     */
    public const XML_PATH_PRODUCT_URL_REWRITE_HELPER_CLASS = 'global/catalog/product/url_rewrite/helper';

    /**
     * Path to product_url model alias
     */
    public const XML_PATH_PRODUCT_URL_MODEL = 'global/catalog/product/url/model';

    /**
     * Path to category_url model alias
     */
    public const XML_PATH_CATEGORY_URL_MODEL = 'global/catalog/category/url/model';

    /**
     * Returns category url rewrite helper instance
     *
     * @return Mage_Catalog_Helper_Category_Url_Rewrite_Interface
     */
    public function getCategoryUrlRewriteHelper()
    {
        $model = $this->getHelper(
            (string) $this->_config->getNode(self::XML_PATH_CATEGORY_URL_REWRITE_HELPER_CLASS),
        );
        if (!$model instanceof Mage_Catalog_Helper_Category_Url_Rewrite_Interface) {
            throw new Mage_Core_Exception('Invalid category URL rewrite helper configured');
        }
        return $model;
    }

    /**
     * Returns product url rewrite helper instance
     *
     * @return Mage_Catalog_Helper_Product_Url_Rewrite_Interface
     */
    public function getProductUrlRewriteHelper()
    {
        $model = $this->getHelper(
            (string) $this->_config->getNode(self::XML_PATH_PRODUCT_URL_REWRITE_HELPER_CLASS),
        );
        if (!$model instanceof Mage_Catalog_Helper_Product_Url_Rewrite_Interface) {
            throw new Mage_Core_Exception('Invalid product URL rewrite helper configured');
        }
        return $model;
    }

    /**
     * Retrieve product_url instance
     *
     * @return Mage_Catalog_Model_Product_Url
     */
    public function getProductUrlInstance()
    {
        $model = $this->getModel(
            (string) $this->_config->getNode(self::XML_PATH_PRODUCT_URL_MODEL),
        );
        assert($model instanceof \Mage_Catalog_Model_Product_Url);
        return $model;
    }

    /**
     * Retrieve category_url instance
     *
     * @return Mage_Catalog_Model_Category_Url
     */
    public function getCategoryUrlInstance()
    {
        $model = $this->getModel(
            (string) $this->_config->getNode(self::XML_PATH_CATEGORY_URL_MODEL),
        );
        assert($model instanceof \Mage_Catalog_Model_Category_Url);
        return $model;
    }
}
