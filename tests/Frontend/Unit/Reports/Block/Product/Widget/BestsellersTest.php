<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function bestsellerOrder(int $productId, int $storeId, int $qty): Mage_Sales_Model_Order
{
    $token = strtoupper(bin2hex(random_bytes(6)));
    $order = Mage::getModel('sales/order')
        ->setStoreId($storeId)
        ->setState(Mage_Sales_Model_Order::STATE_NEW)
        ->setStatus('pending')
        ->setCustomerIsGuest(true)
        ->setCustomerEmail("buyer.{$token}@example.com")
        ->setOrderCurrencyCode('USD')
        ->setBaseCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setStoreCurrencyCode('USD')
        ->setGrandTotal(1)
        ->setBaseGrandTotal(1)
        ->setIncrementId('BEST' . $token);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'product_id' => $productId,
        'store_id' => $storeId,
        'sku' => 'BEST-' . $token,
        'name' => 'Bestseller',
        'qty_ordered' => $qty,
        'price' => 1,
        'base_price' => 1,
        'row_total' => $qty,
        'base_row_total' => $qty,
        'product_type' => 'simple',
    ]));
    $order->save();
    return $order;
}

describe('Bestsellers widget block', function () {
    beforeEach(function () {
        $this->block = new Mage_Reports_Block_Product_Widget_Bestsellers();
    });

    it('exposes sensible defaults', function () {
        expect($this->block->getPeriod())->toBe('all_time');
        expect($this->block->getProductsCount())->toBe(5);
        expect($this->block->onlyInStock())->toBeTrue();
    });

    it('includes the period in the cache key so different periods cache separately', function () {
        $this->block->setPeriod('last_7_days');
        expect($this->block->getCacheKeyInfo())->toContain('last_7_days');

        $this->block->setPeriod('year');
        expect($this->block->getCacheKeyInfo())->toContain('year');
    });

    it('keys the cache by store date so rolling periods refresh daily', function () {
        $today = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect($this->block->getCacheKeyInfo())->toContain($today);
    });

    it('puts the most sold product first, and ignores canceled orders and other stores', function () {
        $prepare = new ReflectionMethod($this->block, '_prepareStorefrontCollection');
        $prepare->setAccessible(true);

        // Products the storefront collection accepts, so the orders below are the only variable
        $candidateIds = Mage::getResourceModel('catalog/product_collection')
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
            ->addStoreFilter()
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize(20)
            ->getAllIds();
        $productIds = [];
        foreach ($candidateIds as $candidateId) {
            if ($prepare->invoke($this->block, [(int) $candidateId])->getSize() === 1) {
                $productIds[] = (int) $candidateId;
            }
            if (count($productIds) === 2) {
                break;
            }
        }
        if (count($productIds) < 2) {
            $this->markTestSkipped('Fewer than two visible, enabled, in-stock products to order.');
        }
        [$bestseller, $decoy] = $productIds;

        $storeId = (int) Mage::app()->getStore()->getId();
        $otherStoreIds = array_diff(array_map(intval(...), array_keys(Mage::app()->getStores())), [$storeId]);

        // Other suites leave orders behind, so out-sell every one of them rather than assume none
        $orders = [bestsellerOrder($bestseller, $storeId, 1000000)];
        $orders[] = bestsellerOrder($decoy, $storeId, 3000000)->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
        if ($otherStoreIds) {
            $orders[] = bestsellerOrder($decoy, (int) reset($otherStoreIds), 2000000);
        }

        try {
            $method = new ReflectionMethod($this->block, '_getProductCollection');
            $method->setAccessible(true);
            $collection = $method->invoke($this->block);

            expect($collection)->toBeInstanceOf(Mage_Catalog_Model_Resource_Product_Collection::class)
                ->and((int) $collection->getFirstItem()->getId())->toBe($bestseller);
        } finally {
            Mage::register('isSecureArea', true, true);
            foreach ($orders as $order) {
                $order->delete();
            }
            Mage::unregister('isSecureArea');
        }
    });

    it('orders products by an explicit id list using portable SQL', function () {
        $ids = Mage::getResourceModel('catalog/product_collection')
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
            ->addStoreFilter()
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->getAllIds();
        $ids = array_values(array_map('intval', $ids));

        if (count($ids) < 2) {
            $this->markTestSkipped('Not enough enabled products to assert ordering.');
        }

        // Reverse and trim so the requested order differs from the natural id order.
        $ids = array_slice(array_reverse($ids), 0, 5);

        $method = new ReflectionMethod($this->block, '_getOrderByIdsExpr');
        $method->setAccessible(true);
        $expr = $method->invoke($this->block, $ids);

        $collection = Mage::getResourceModel('catalog/product_collection')->addIdFilter($ids);
        $collection->getSelect()->order($expr);

        $loaded = array_values(array_map(fn($p) => (int) $p->getId(), $collection->getItems()));
        expect($loaded)->toBe($ids);
    });
});
