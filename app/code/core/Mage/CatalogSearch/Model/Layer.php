<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogSearch_Model_Layer extends Mage_Catalog_Model_Layer
{
    public const XML_PATH_DISPLAY_LAYER_COUNT = 'catalog/search/use_layered_navigation_count';

    /**
     * Get current layer product collection
     *
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Collection
     */
    #[\Override]
    public function getProductCollection()
    {
        if (isset($this->_productCollections[$this->getCurrentCategory()->getId()])) {
            $collection = $this->_productCollections[$this->getCurrentCategory()->getId()];
        } else {
            $collection = Mage::helper('catalogsearch')->getEngine()->getResultCollection();
            $this->prepareProductCollection($collection);
            $this->_productCollections[$this->getCurrentCategory()->getId()] = $collection;
        }
        return $collection;
    }

    /**
     * Prepare product collection
     *
     * @param Mage_CatalogSearch_Model_Resource_Fulltext_Collection $collection
     * @return Mage_Catalog_Model_Layer
     */
    #[\Override]
    public function prepareProductCollection($collection)
    {
        $collection
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addSearchFilter(Mage::helper('catalogsearch')->getQuery()->getQueryText())
            ->setStore(Mage::app()->getStore())
            ->addPriceData()
            ->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($collection);

        return $this;
    }

    /**
     * Get layer state key
     *
     * @return string
     */
    #[\Override]
    public function getStateKey()
    {
        if ($this->_stateKey === null) {
            $this->_stateKey = 'Q_' . Mage::helper('catalogsearch')->getQuery()->getId()
                . '_' . parent::getStateKey();
        }
        return $this->_stateKey;
    }

    /**
     * Get default tags for current layer state
     *
     * @return  array
     */
    #[\Override]
    public function getStateTags(array $additionalTags = [])
    {
        $additionalTags = parent::getStateTags($additionalTags);
        $additionalTags[] = Mage_CatalogSearch_Model_Query::CACHE_TAG;
        return $additionalTags;
    }

    /**
     * Add filters to attribute collection
     *
     * @param   Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection
     * @return  Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    #[\Override]
    protected function _prepareAttributeCollection($collection)
    {
        $collection->addIsFilterableInSearchFilter()
            ->addVisibleFilter();
        return $collection;
    }

    /**
     * Filter which attributes are included in getFilterableAttributes
     *
     */
    #[\Override]
    protected function _filterFilterableAttributes(Mage_Catalog_Model_Resource_Eav_Attribute  $attribute): bool
    {
        return $attribute->getIsVisible() && $attribute->getIsFilterableInSearch() > 0;
    }

    /**
     * Prepare attribute for use in layered navigation
     *
     * @param   Mage_Eav_Model_Entity_Attribute $attribute
     * @return  Mage_Eav_Model_Entity_Attribute
     */
    #[\Override]
    protected function _prepareAttribute($attribute)
    {
        $attribute = parent::_prepareAttribute($attribute);
        $attribute->setIsFilterable(Mage_Catalog_Model_Layer_Filter_Attribute::OPTIONS_ONLY_WITH_RESULTS);
        return $attribute;
    }
}
