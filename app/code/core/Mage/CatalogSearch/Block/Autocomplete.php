<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Autocomplete queries list
 *
 * @package    Mage_CatalogSearch
 */
class Mage_CatalogSearch_Block_Autocomplete extends Mage_Core_Block_Template
{
    protected ?Mage_CatalogSearch_Model_Resource_Fulltext_Collection $productCollection = null;

    public function getLoadedProductCollection(): Mage_CatalogSearch_Model_Resource_Fulltext_Collection
    {
        if (!$this->productCollection) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = $this->helper('catalogsearch');
            $query = $helper->getQueryText();

            $productCollection = Mage::getResourceModel('catalogsearch/fulltext_collection');
            $productCollection->addSearchFilter($query)
                ->setOrder('relevance', 'desc')
                ->setPageSize(10);
            Mage::getModel('catalog/layer')->prepareProductCollection($productCollection);

            $this->productCollection = $productCollection;
        }

        return $this->productCollection;
    }
}
