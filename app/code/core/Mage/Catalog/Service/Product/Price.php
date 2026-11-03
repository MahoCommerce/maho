<?php

/**
 * Replace the tier prices and the group prices of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;

/**
 * The backend models of tier_price and group_price check each row, so the admin save gets the same rules.
 */
class Mage_Catalog_Service_Product_Price
{
    /**
     * Replace the tier prices of $product with $rows. With a list of $websiteIds, each row must be for one of these
     * websites, and the rows of the other websites stay.
     *
     * @param list<array{website_id: int, cust_group: int, price_qty: float, price: float}> $rows
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Forbidden for a row of a website outside $websiteIds
     * @throws Mage_Core_Exception for an invalid row or a duplicate
     */
    public function setTierPrices(Product $product, array $rows, ?array $websiteIds = null): void
    {
        $this->replace($product, 'tier_price', $rows, $websiteIds);
    }

    /**
     * Replace the group prices of $product with $rows. With a list of $websiteIds, each row must be for one of these
     * websites, and the rows of the other websites stay.
     *
     * @param list<array{website_id: int, cust_group: int, price: float}> $rows
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Forbidden for a row of a website outside $websiteIds
     * @throws Mage_Core_Exception for an invalid row or a duplicate
     */
    public function setGroupPrices(Product $product, array $rows, ?array $websiteIds = null): void
    {
        $this->replace($product, 'group_price', $rows, $websiteIds);
    }

    /**
     * The backend deletes only the rows of the scope that the product is loaded in, so the product is loaded in the
     * admin scope, where it holds the rows of all websites.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<int>|null $websiteIds
     */
    private function replace(Product $product, string $code, array $rows, ?array $websiteIds): void
    {
        $helper = Mage::helper('catalog');
        if ($websiteIds !== null) {
            foreach ($rows as $row) {
                if (!in_array((int) $row['website_id'], $websiteIds, true)) {
                    throw new Mage_Core_Exception_Forbidden($code === 'tier_price'
                        ? $helper->__('Access denied for the requested tier-price website')
                        : $helper->__('Access denied for the requested group-price website'));
                }
            }
        }

        $product = Mage::getService('catalog/product')->getById((int) $product->getId());
        $backend = $product->getResource()->getAttribute($code)->getBackend();
        if (!is_array($product->getOrigData($code))) {
            $backend->afterLoad($product);
        }

        if ($websiteIds !== null) {
            $kept = array_filter(
                (array) $product->getOrigData($code),
                fn(array $row): bool => !in_array((int) ($row['website_id'] ?? 0), $websiteIds, true),
            );
            $rows = array_merge(array_values($kept), $rows);
        }

        $product->setData($code, $rows);
        $backend->validate($product);
        $product->save();
    }
}
