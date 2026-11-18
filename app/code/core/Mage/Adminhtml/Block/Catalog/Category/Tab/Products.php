<?php

/**
 * Products tab of the category: the grid and the tile view of the same products.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Catalog_Category_Tab_Products extends Mage_Adminhtml_Block_Template
{
    protected ?Mage_Adminhtml_Block_Catalog_Category_Tab_Product $gridBlock = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/category/tab/products.phtml');
    }

    public function getCategory(): Mage_Catalog_Model_Category
    {
        return Mage::registry('category');
    }

    public function getGridBlock(): Mage_Adminhtml_Block_Catalog_Category_Tab_Product
    {
        $this->gridBlock ??= $this->getLayout()->createBlock('adminhtml/catalog_category_tab_product', 'category.product.grid');
        return $this->gridBlock;
    }

    /**
     * A new category has no products to arrange yet, so it has no tile view.
     */
    public function hasTiles(): bool
    {
        return (bool) $this->getCategory()->getId();
    }

    /**
     * Retrieve the settings of the script of the tile view.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $category = $this->getCategory();
        $merchandiser = Mage::getModel('catalog/category_merchandiser');
        $helper = Mage::helper('catalog');
        $isManual = $merchandiser->isManual($category);
        $storeId = (int) $category->getStoreId();
        $days = Mage::getSingleton('catalog/config')->getSortDays();

        $readonlyMessage = '';
        if (!$isManual) {
            $readonlyMessage = $helper->__(
                'This category sorts by %s. Set Default Product Listing Sort By to %s to change the order by hand.',
                $merchandiser->getSortLabel($category),
                $helper->__('Best Value'),
            );
        }

        return [
            'tilesUrl' => $this->getUrl('*/catalog_category/tiles', ['id' => $category->getId(), 'store' => $storeId ?: null]),
            'searchUrl' => $this->getUrl('*/catalog_category/tileSearch', ['id' => $category->getId(), 'store' => $storeId ?: null]),
            'isManual' => $isManual,
            'isDynamic' => (bool) $category->getData('is_dynamic'),
            'isReadonly' => (bool) $category->getProductsReadonly(),
            'hasStore' => $storeId > 0,
            'readonlyMessage' => $readonlyMessage,
            'perPage' => $merchandiser->getPerPage($category),
            'placement' => $category->getNewProductPlacement(),
            'outOfStockLast' => $category->getOutOfStockLast(),
            'pinned' => $category->getPinnedProducts(),
            'salesDays' => $days,
            'salesUpdatedAt' => $this->formatUpdatedAt($merchandiser->getUpdatedAt('bestsellers')),
            'viewsUpdatedAt' => $merchandiser->getSort($category) === Mage_Catalog_Model_Config::SORT_MOST_VIEWED
                ? $this->formatUpdatedAt($merchandiser->getUpdatedAt('viewed'))
                : null,
            'labels' => $this->getLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getLabels(): array
    {
        $helper = Mage::helper('catalog');
        $days = Mage::getSingleton('catalog/config')->getSortDays();

        return [
            'view' => $helper->__('Products view'),
            'grid' => $helper->__('Grid'),
            'tiles' => $helper->__('Tiles'),
            'global' => $helper->__('The manual order is the same in all store views.'),
            'outOfStockLast' => $helper->__('Out of stock products show last on the storefront.'),
            'unsaved' => $helper->__('The order has unsaved changes. Click Save Category to keep them.'),
            'confirmLeave' => $helper->__('The order of the products has unsaved changes. Discard them?'),
            'search' => $helper->__('Search by name or SKU'),
            'outOfStockOnly' => $helper->__('Out of stock only'),
            'add' => $helper->__('Add a product by name or SKU'),
            'remove' => $helper->__('Remove'),
            'reset' => $helper->__('Reset'),
            'moveTop' => $helper->__('Move to top'),
            'moveBottom' => $helper->__('Move to bottom'),
            'movePosition' => $helper->__('Move to position'),
            'pin' => $helper->__('Pin'),
            'unpin' => $helper->__('Unpin'),
            'position' => $helper->__('Position'),
            'select' => $helper->__('Select'),
            'selected' => $helper->__('Selected: %s'),
            'inStock' => $helper->__('In stock'),
            'outOfStock' => $helper->__('Out of stock'),
            'disabled' => $helper->__('Disabled'),
            'byRule' => $helper->__('Added by a rule'),
            'sold' => $helper->__('Units sold, last %s days', $days),
            'revenue' => $helper->__('Revenue, last %s days', $days),
            'endOfPage' => $helper->__('End of page %s'),
            'empty' => $helper->__('No products'),
            'salesUpdated' => $helper->__('Units sold and revenue updated: %s'),
            'viewsUpdated' => $helper->__('Views updated: %s'),
            'noStatistics' => $helper->__('The statistics are not updated yet.'),
            'noResults' => $helper->__('No product found'),
            'error' => $helper->__('The products could not be loaded.'),
        ];
    }

    protected function formatUpdatedAt(?string $updatedAt): string
    {
        return $updatedAt === null
            ? ''
            : Mage::helper('core')->formatDate($updatedAt, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, true);
    }
}
