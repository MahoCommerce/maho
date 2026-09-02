<?php

/**
 * Builds the markdown for a category page from the category model and its product list.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_Category extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    /** One document per category, so the list is capped instead of paged */
    public const PRODUCTS_LIMIT = 100;

    #[\Override]
    public function render(): ?string
    {
        $category = $this->getCategory();
        if ($category === null) {
            return null;
        }

        $sections = [$this->heading((string) $category->getName(), (string) $category->getMetaDescription())];

        $html = (string) Mage::helper('catalog/output')->categoryAttribute($category, $category->getDescription(), 'description');
        $description = $this->toMarkdown($html);
        if ($description !== '') {
            $sections[] = $description;
        }

        $landingPage = $this->landingPage($category);
        if ($landingPage !== '') {
            $sections[] = $landingPage;
        }

        $children = [];
        foreach ($category->getChildrenCategories() as $child) {
            $children[] = '- ' . $this->link((string) $child->getName(), (string) $child->getUrl());
        }
        if ($children !== []) {
            $sections[] = $this->section($this->__('Subcategories'), implode("\n", $children));
        }

        $products = $this->products($category);
        if ($products !== '') {
            $sections[] = $this->section($this->__('Products'), $products);
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        $category = $this->getCategory();
        $tags = $category?->getCacheTags() ?: [];
        $tags[] = Mage_Catalog_Model_Product::CACHE_TAG;
        if ($category?->getLandingPage()) {
            $tags[] = Mage_Cms_Model_Block::CACHE_TAG . '_' . $category->getLandingPage();
        }

        return $tags;
    }

    private function getCategory(): ?Mage_Catalog_Model_Category
    {
        $category = Mage::registry('current_category');

        return $category instanceof Mage_Catalog_Model_Category && $category->getId() ? $category : null;
    }

    /**
     * The CMS block the HTML page shows in "static block only" and "static block and products" mode.
     */
    private function landingPage(Mage_Catalog_Model_Category $category): string
    {
        $mode = $category->getDisplayMode();
        if (($mode !== Mage_Catalog_Model_Category::DM_PAGE && $mode !== Mage_Catalog_Model_Category::DM_MIXED)
            || !$category->getLandingPage()
        ) {
            return '';
        }

        $html = Mage::app()->getLayout()->createBlock('cms/block')
            ->setBlockId($category->getLandingPage())
            ->toHtml();

        return $this->toMarkdown((string) $html);
    }

    /**
     * The first products of the category in position order, independent of the page, sort and
     * layered navigation filters of the HTML request.
     */
    private function products(Mage_Catalog_Model_Category $category): string
    {
        if ($category->getDisplayMode() === Mage_Catalog_Model_Category::DM_PAGE) {
            return '';
        }

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setStoreId(Mage::app()->getStore()->getId())
            ->addCategoryFilter($category)
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
            ->addPriceData()
            ->addTaxPercents()
            ->addUrlRewrite($category->getId())
            ->addAttributeToSort('position', Maho\Db\Select::SQL_ASC)
            ->setPageSize(self::PRODUCTS_LIMIT)
            ->setCurPage(1);

        $table = $this->productTable($collection);
        if ($table === '') {
            return $this->__('There are no products in this category.');
        }

        $more = $collection->getSize() - $collection->count();
        if ($more > 0) {
            $table .= "\n\n" . $this->__('and %s more products, listed in the XML sitemap', $more);
        }

        return $table;
    }
}
