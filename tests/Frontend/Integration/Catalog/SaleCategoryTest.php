<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('Sale Category Integration', function () {
    test('sale category exists and contains products', function () {
        // Find the Sale category
        $saleCategory = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Sale')
            ->addIsActiveFilter()
            ->getFirstItem();

        expect($saleCategory->getId())->not()->toBeNull()
            ->and($saleCategory->getName())->toBe('Sale')
            ->and($saleCategory->getIsActive())->toBe('1');

        // Get products in the Sale category
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addCategoryFilter($saleCategory)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', [
                'in' => [
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                ],
            ]);

        // Verify the Sale category contains products
        expect($productCollection->getSize())->toBeGreaterThan(0);
    });

    test('sale category products are properly configured', function () {
        // Find the Sale category
        $saleCategory = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Sale')
            ->addIsActiveFilter()
            ->getFirstItem();

        // Skip if Sale category doesn't exist (sample data not installed)
        if (!$saleCategory->getId()) {
            $this->markTestSkipped('Sale category not found - sample data may not be installed');
        }

        // Get products in the Sale category
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'sku', 'price', 'status', 'visibility'])
            ->addCategoryFilter($saleCategory)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize(5); // Limit to first 5 products for testing

        foreach ($productCollection as $product) {
            // Verify each product has required attributes
            expect($product->getName())->not()->toBeEmpty()
                ->and($product->getSku())->not()->toBeEmpty()
                ->and($product->getStatus())->toBe((string) Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->and($product->getVisibility())->toBeIn([
                    (string) Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                    (string) Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    (string) Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                ]);
        }
    });

    test('sale category is accessible via frontend', function () {
        // Find the Sale category
        $saleCategory = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Sale')
            ->addIsActiveFilter()
            ->getFirstItem();

        // Skip if Sale category doesn't exist
        if (!$saleCategory->getId()) {
            $this->markTestSkipped('Sale category not found - sample data may not be installed');
        }

        // Verify category has required frontend attributes
        expect($saleCategory->getIsActive())->toBe('1')
            ->and($saleCategory->getIncludeInMenu())->toBe('1')
            ->and($saleCategory->getUrlKey())->not()->toBeEmpty();

        // Verify category URL can be generated
        $categoryUrl = $saleCategory->getUrl();
        expect($categoryUrl)->not()->toBeEmpty()
            ->and($categoryUrl)->toContain($saleCategory->getUrlKey());
    });

    test('sale category products have special prices or are on sale', function () {
        // Find the Sale category
        $saleCategory = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Sale')
            ->addIsActiveFilter()
            ->getFirstItem();

        // Skip if Sale category doesn't exist
        if (!$saleCategory->getId()) {
            $this->markTestSkipped('Sale category not found - sample data may not be installed');
        }

        // Get products in the Sale category
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'price', 'special_price', 'special_from_date', 'special_to_date'])
            ->addCategoryFilter($saleCategory)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize(10);

        $productsWithSpecialPrice = 0;
        $totalProducts = $productCollection->getSize();

        foreach ($productCollection as $product) {
            $specialPrice = $product->getSpecialPrice();
            $regularPrice = $product->getPrice();

            // Check if product has a special price that's lower than regular price
            if ($specialPrice && $specialPrice < $regularPrice) {
                // Verify special price dates if set
                $specialFromDate = $product->getSpecialFromDate();
                $specialToDate = $product->getSpecialToDate();

                $now = Mage_Core_Model_Locale::now();
                $isSpecialPriceActive = true;

                if ($specialFromDate && $specialFromDate > $now) {
                    $isSpecialPriceActive = false;
                }

                if ($specialToDate && $specialToDate < $now) {
                    $isSpecialPriceActive = false;
                }

                if ($isSpecialPriceActive) {
                    $productsWithSpecialPrice++;
                }
            }
        }

        // Verify that at least some products in the Sale category have special prices
        // This is a reasonable expectation for a "Sale" category
        if ($totalProducts > 0) {
            expect($productsWithSpecialPrice)->toBeGreaterThan(0);
        }
    });
});
