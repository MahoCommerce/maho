<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The delete of a compare item dispatches catalog_product_compare_remove_product and counts the compare list of the
 * session again, in the model (#1348). So the storefront and the admin order create get both, one time each.
 */

const COMPARE_DELETE_SPY = 'tests/compare_delete_spy';

beforeEach(function (): void {
    $this->spy = new class {
        /** @var list<int> */
        public array $productIds = [];

        public function record(\Maho\Event\Observer $observer): void
        {
            $this->productIds[] = (int) $observer->getEvent()->getProduct()->getProductId();
        }
    };
    Mage::register('_singleton/' . COMPARE_DELETE_SPY, $this->spy, true);
    (function (): void {
        $this->_events['global']['catalog_product_compare_remove_product'] = ['observers' => [
            'compare_delete_spy' => ['type' => 'singleton', 'model' => COMPARE_DELETE_SPY, 'method' => 'record'],
        ]];
    })->call(Mage::app());

    $this->productId = (int) Mage::getModel('catalog/product')->getCollection()->getFirstItem()->getId();
    $this->item = Mage::getModel('catalog/product_compare_item')
        ->setCustomerId(null)
        ->setVisitorId(987655)
        ->setProductId($this->productId)
        ->setStoreId((int) Mage::app()->getStore('default')->getId())
        ->save();
    Mage::getSingleton('log/visitor')->setData('visitor_id', '987655');
    Mage::getSingleton('catalog/session')->setCatalogCompareItemsCount(5);
});

afterEach(function (): void {
    (function (): void {
        unset($this->_events['global']['catalog_product_compare_remove_product']);
    })->call(Mage::app());
    Mage::unregister('_singleton/' . COMPARE_DELETE_SPY);
    Mage::getSingleton('catalog/session')->unsCatalogCompareItemsCount();
    Mage::getModel('catalog/product_compare_item')->load($this->item->getId())->delete();
});

it('dispatches the event one time and counts the list again when the admin order create removes a compare item', function (): void {
    Mage::getModel('adminhtml/sales_order_create')->removeItem((int) $this->item->getId(), 'compared');

    expect($this->spy->productIds)->toBe([$this->productId])
        ->and((int) Mage::getSingleton('catalog/session')->getCatalogCompareItemsCount())->toBe(0)
        ->and(Mage::getModel('catalog/product_compare_item')->load($this->item->getId())->getId())->toBeNull();
});

it('dispatches the event one time when the model deletes a compare item', function (): void {
    $this->item->delete();

    expect($this->spy->productIds)->toBe([$this->productId])
        ->and((int) Mage::getSingleton('catalog/session')->getCatalogCompareItemsCount())->toBe(0);
});
