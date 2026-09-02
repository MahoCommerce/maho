<?php

/**
 * Builds the markdown for a category page from the category model and the rendered product list.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_Category extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    #[\Override]
    public function render(): ?string
    {
        $category = $this->getCategory();
        if ($category === null) {
            return null;
        }

        $sections = [$this->heading((string) $category->getName())];

        $html = (string) Mage::helper('catalog/output')->categoryAttribute($category, $category->getDescription(), 'description');
        $description = $this->toMarkdown($html);
        if ($description !== '') {
            $sections[] = $description;
        }

        $children = [];
        foreach ($category->getChildrenCategories() as $child) {
            $children[] = '- ' . $this->link((string) $child->getName(), (string) $child->getUrl());
        }
        if ($children !== []) {
            $sections[] = $this->section($this->__('Subcategories'), implode("\n", $children));
        }

        $products = $this->products();
        if ($products !== '') {
            $sections[] = $this->section($this->__('Products'), $products);
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        $tags = $this->getCategory()?->getCacheTags() ?: [];
        $tags[] = Mage_Catalog_Model_Product::CACHE_TAG;

        return $tags;
    }

    private function getCategory(): ?Mage_Catalog_Model_Category
    {
        $category = Mage::registry('current_category');

        return $category instanceof Mage_Catalog_Model_Category && $category->getId() ? $category : null;
    }

    /**
     * Reuses the collection the HTML page loaded, with its page, sort and layered navigation filters.
     */
    private function products(): string
    {
        $layout = Mage::app()->getLayout();
        $view = $layout->getBlock('category.products');
        if (!$view instanceof Mage_Catalog_Block_Category_View || !($view->isProductMode() || $view->isMixedMode())) {
            return '';
        }
        $list = $layout->getBlock('product_list');
        if (!$list instanceof Mage_Catalog_Block_Product_List) {
            return '';
        }

        $table = $this->productTable($list->getLoadedProductCollection());
        if ($table === '') {
            return $this->__('There are no products in this category.');
        }

        $toolbar = $layout->getBlock('product_list_toolbar');
        if ($toolbar instanceof Mage_Catalog_Block_Product_List_Toolbar && $toolbar->getCollection()) {
            $current = (int) $toolbar->getCurrentPage();
            $last = (int) $toolbar->getLastPageNum();
            if ($last > 1) {
                $pager = $this->__('Page %s of %s', $current, $last);
                if ($current < $last) {
                    $pager .= '. ' . $this->link($this->__('Next page'), $this->pageUrl((string) $toolbar->getPagerUrl(['p' => $current + 1])));
                }
                $table .= "\n\n" . $pager;
            }
        }

        return $table;
    }
}
