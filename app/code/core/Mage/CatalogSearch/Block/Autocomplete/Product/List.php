<?php

class Mage_CatalogSearch_Block_Autocomplete_Product_List extends Mage_Catalog_Block_Product_List
{
    public function getMode(): string
    {
        return 'list';
    }

    public function getToolbarHtml(): string
    {
        return '';
    }

    public function getLoadedProductCollection(): Mage_CatalogSearch_Model_Resource_Fulltext_Collection
    {
        if (!$this->_productCollection) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = $this->helper('catalogsearch');
            $query = $helper->getQueryText();

            $productCollection = Mage::getResourceModel('catalogsearch/fulltext_collection');
            $productCollection->addSearchFilter($query)
                ->setOrder('relevance', 'desc')
                ->setPageSize(10);
            Mage::getModel('catalog/layer')->prepareProductCollection($productCollection);

            $this->_productCollection = $productCollection;
        }

        return $this->_productCollection;
    }
}