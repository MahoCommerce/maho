<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Block_Autocomplete_Product_List extends Mage_Catalog_Block_Product_List
{
    #[\Override]
    public function getMode(): string
    {
        return 'list';
    }

    #[\Override]
    public function getToolbarHtml(): string
    {
        return '';
    }

    #[\Override]
    public function getLoadedProductCollection(): Mage_CatalogSearch_Model_Resource_Fulltext_Collection
    {
        if (!$this->_productCollection) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = $this->helper('catalogsearch');
            $query = $helper->getQueryText();

            /** @var Mage_CatalogSearch_Model_Resource_Fulltext_Collection $productCollection */
            $productCollection = Mage::getResourceModel('catalogsearch/fulltext_collection');
            $productCollection->addSearchFilter($query)
                ->setOrder('relevance', 'desc')
                ->setPageSize(10);
            Mage::getModel('catalog/layer')->prepareProductCollection($productCollection);

            $this->_productCollection = $productCollection;
        }

        /** @var Mage_CatalogSearch_Model_Resource_Fulltext_Collection */
        return $this->_productCollection;
    }
}
