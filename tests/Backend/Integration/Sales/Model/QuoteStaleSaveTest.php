<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->product = Mage::getModel('catalog/product')
        ->setStoreId(0)
        ->setWebsiteIds([1])
        ->setAttributeSetId((int) Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setSku('quote-stale-save-' . bin2hex(random_bytes(4)))
        ->setName('Example product')
        ->setPrice(10)
        ->setStockData(['qty' => 10, 'is_in_stock' => 1])
        ->save();
    $product = Mage::getModel('catalog/product')->setStoreId(1)->load($this->product->getId());
    $this->quote = createPlaceableQuote($product, 1);
});

afterEach(function (): void {
    $order = Mage::getModel('sales/order')->load($this->quote->getId(), 'quote_id');
    if ($order->getId()) {
        $order->delete();
    }
    $this->quote->delete();
    $this->product->delete();
});

it('keeps the cart closed when an older copy saves a customer note', function (): void {
    $quoteId = (int) $this->quote->getId();
    $older = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($older->getIsActive())->toBeTrue()
        ->and($older->getReservedOrderId())->toBeNull();

    $order = Mage::getService('sales/order')->submitWithoutNotice($this->quote, Mage_Checkout_Model_Actor::Staff);
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe($order->getIncrementId());

    $older->setCustomerNote('Example note')->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $placed = Mage::getModel('sales/order')->load($order->getId());
    expect([
        'active' => $stored->getIsActive(),
        'reservation' => $stored->getReservedOrderId(),
        'note' => $stored->getCustomerNote(),
    ])->toBe([
        'active' => false,
        'reservation' => $order->getIncrementId(),
        'note' => 'Example note',
    ])->and($placed->getQuoteId())->toBe($quoteId)
        ->and($placed->getIncrementId())->toBe($order->getIncrementId());
});

it('allows a quote adapter to keep a previously loaded cart active', function (): void {
    $quoteId = (int) $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = new class ($loaded) extends Mage_Sales_Model_Service_Quote {
        #[\Override]
        protected $_shouldInactivateQuote = false;
    };

    $order = $adapter->submitOrder();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBe($order->getIncrementId());
});

it('closes and reopens a loaded cart and clears its reservation on the second save', function (): void {
    $quoteId = (int) $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);

    $loaded->reserveOrderId()->setIsActive(false)->save();
    $reserved = $loaded->getReservedOrderId();
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($reserved)->not->toBeNull()
        ->and($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe($reserved);

    $loaded->setIsActive()->setReservedOrderId(null)->save();
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull();
});

it('allows using setIsActive to reopen a cart explicitly even when the object holds the same value', function (): void {
    $quoteId = (int) $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $this->quote->setIsActive(false)->save();

    $loaded->setIsActive()->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue();
});

it('retries a rolled back cart save and preserves newer state on an unrelated save afterward', function (): void {
    $quoteId = (int) $this->quote->getId();
    $loaded = new class extends Mage_Sales_Model_Quote {
        public bool $failAfterSave = true;

        #[\Override]
        protected function _afterSave()
        {
            parent::_afterSave();
            if ($this->failAfterSave) {
                throw new RuntimeException('Example persistence failure');
            }
            return $this;
        }
    };
    $loaded->loadByIdWithoutStore($quoteId)->reserveOrderId()->setIsActive(false);
    $reserved = $loaded->getReservedOrderId();
    expect(fn() => $loaded->save())->toThrow(RuntimeException::class, 'Example persistence failure');

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($loaded->getIsActive())->toBeFalse()
        ->and($loaded->getReservedOrderId())->toBe($reserved);

    $loaded->failAfterSave = false;
    $loaded->save();
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()->and($stored->getReservedOrderId())->toBe($reserved);

    $stored->setIsActive()->setReservedOrderId(null)->save();
    $loaded->setCustomerNote('Example note')->save();
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($stored->getCustomerNote())->toBe('Example note');
});

it('preserves the lifecycle updates from two saves in one outer transaction', function (): void {
    $quoteId = (int) $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $resource = $loaded->getResource();
    $resource->beginTransaction();
    try {
        $loaded->reserveOrderId()->setIsActive(false)->save();
        $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
        expect($stored->getIsActive())->toBeFalse()
            ->and($stored->getReservedOrderId())->not->toBeNull();

        $loaded->setIsActive()->setReservedOrderId(null)->save();
        $resource->commit();
    } catch (Throwable $exception) {
        $resource->rollBack();
        throw $exception;
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull();
});
