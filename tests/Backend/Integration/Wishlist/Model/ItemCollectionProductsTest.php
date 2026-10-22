<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A wishlist item without item options (as the REST API and imports save them) must get its
 * product from the collection, and must survive the next save of its wishlist.
 */

beforeEach(function (): void {
    $products = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter(1)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(2);
    $this->productIds = array_map('intval', $products->getColumnValues('entity_id'));
    if (count($this->productIds) < 2) {
        $this->markTestSkipped('Two visible simple products are needed');
    }

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setFirstname('Pest')
        ->setLastname('Wishlist Collection')
        ->setEmail('pest-wishlist-collection-' . uniqid() . '@example.com');
    $customer->save();
    $this->customer = $customer;

    $this->wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer((int) $customer->getId(), true);

    $this->plainItem = Mage::getModel('wishlist/item')
        ->setWishlistId((int) $this->wishlist->getId())
        ->setProductId($this->productIds[0])
        ->setStoreId(1)
        ->setQty(1);
    $this->plainItem->save();
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('assigns the product to an item that has no options', function (): void {
    $item = Mage::getModel('wishlist/item')->getCollection()
        ->setWishlist($this->wishlist)
        ->getItemById($this->plainItem->getId());

    expect($item->isDeleted())->toBeFalse()
        ->and((int) $item->getProduct()->getId())->toBe($this->productIds[0]);
});

it('keeps an item that has no options when another product is added to the wishlist', function (): void {
    $wishlist = Mage::getModel('wishlist/wishlist')->load($this->wishlist->getId());
    $wishlist->addNewItem($this->productIds[1]);
    $wishlist->save();

    $productIds = Mage::getModel('wishlist/item')->getCollection()
        ->setWishlist($wishlist)
        ->getColumnValues('product_id');

    expect(array_map('intval', $productIds))->toEqualCanonicalizing($this->productIds);
});
