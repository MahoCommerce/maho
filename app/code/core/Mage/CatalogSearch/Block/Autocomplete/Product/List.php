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

    public function getLoadedProductCollection()
    {
        return $this->_getProductCollection();
    }
}