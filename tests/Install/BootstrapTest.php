<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoInstallTestCase::class);

it('can load Maho install classes and path is set correctly', function () {
    // Test that install classes are available via autoloader
    expect(class_exists('Mage_Install_Model_Installer'))->toBeTrue();
    expect(class_exists('Mage_Install_Controller_Router_Install'))->toBeTrue();

    // Test that Maho root path is set correctly (should point to main Maho directory)
    expect(Mage::getRoot())->toBe(dirname(__DIR__, 2));
});

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
            ->and($saleCategory->getIsActive())->toBe(1);

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

    test('sale category products have special prices or are on sale', function () {
        // Find the Sale category
        $saleCategory = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('*')
            ->addFieldToFilter('name', 'Sale')
            ->addIsActiveFilter()
            ->getFirstItem();

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
