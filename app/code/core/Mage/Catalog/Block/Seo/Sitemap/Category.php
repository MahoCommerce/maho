<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * SEO Categories Sitemap block
 *
 * @package    Mage_Catalog
 *
 * @method $this setCollection(array|Mage_Catalog_Model_Resource_Category_Collection|\Maho\Data\Collection|\Maho\Data\Tree\Node\Collection $value)
 */
class Mage_Catalog_Block_Seo_Sitemap_Category extends Mage_Catalog_Block_Seo_Sitemap_Abstract
{
    /**
     * Initialize categories collection
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $helper = Mage::helper('catalog/category');
        /** @var Mage_Catalog_Helper_Category $helper */
        $collection = $helper->getStoreCategories('name', true, false);
        $this->setCollection($collection);
        return $this;
    }

    /**
     * Get item URL
     *
     * @param Mage_Catalog_Model_Category $category
     * @return string
     */
    #[\Override]
    public function getItemUrl($category)
    {
        $helper = Mage::helper('catalog/category');
        /** @var Mage_Catalog_Helper_Category $helper */
        return $helper->getCategoryUrl($category);
    }
}
