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

it('preserves newer checkout fields on a cart loaded from a collection', function (bool $streamed): void {
    $quoteId = $this->quote->getId();
    $collection = Mage::getResourceModel('sales/quote_collection');
    $collection->getSelect()->where('main_table.entity_id = ?', $quoteId);
    $loaded = $streamed ? $collection->fetchItem() : $collection->getFirstItem();

    $this->quote->setIsActive(false)->setReservedOrderId('example-order')->save();
    $loaded->setCustomerNote('Example note')->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe('example-order')
        ->and($stored->getCustomerNote())->toBe('Example note');
})->with(['collection' => [false], 'streamed collection' => [true]]);

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

it('preserves a newer cart state when an older load recollects totals', function (): void {
    $this->quote->setTriggerRecollect(true)->save();
    $loaded = new class extends Mage_Sales_Model_Quote {
        public Closure $afterHydration;

        #[\Override]
        protected function _afterLoad()
        {
            ($this->afterHydration)();
            return parent::_afterLoad();
        }
    };
    $loaded->afterHydration = fn() => $this->quote->setIsActive(false)->setReservedOrderId('example-order')->save();
    $loaded->loadActive($this->quote->getId());

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId());
    expect($loaded->getTotalsCollectedFlag())->toBeTrue()
        ->and($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe('example-order')
        ->and($stored->getTriggerRecollect())->toBeFalse();
});

it('preserves newer cart state on a direct resource save after an earlier direct save', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $resource = $loaded->getResource();
    $resource->save($loaded->setIsActive()->setReservedOrderId('first-reservation'));

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getReservedOrderId())->toBe('first-reservation');
    $stored->setIsActive(false)->setReservedOrderId('example-order')->save();
    $resource->save($loaded->setCustomerNote('Example note'));

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe('example-order')
        ->and($stored->getCustomerNote())->toBe('Example note');
});

it('writes a resource after-save assignment on the next save of the same cart', function (): void {
    $loaded = new class extends Mage_Sales_Model_Quote {
        public Mage_Sales_Model_Resource_Quote $testResource;

        #[\Override]
        protected function _getResource()
        {
            return $this->testResource;
        }
    };
    $loaded->testResource = new class extends Mage_Sales_Model_Resource_Quote {
        #[\Override]
        protected function _afterSave(Mage_Core_Model_Abstract $object)
        {
            if ($object->getReservedOrderId() === 'first-reservation') {
                $object->setReservedOrderId('next-reservation');
            }
            return parent::_afterSave($object);
        }
    };
    $quoteId = $this->quote->getId();
    $loaded->loadByIdWithoutStore($quoteId)->setReservedOrderId('first-reservation')->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getReservedOrderId())->toBe('first-reservation');

    $loaded->setCustomerNote('Example note')->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getReservedOrderId())->toBe('next-reservation');
});

it('keeps deliberate cart changes available for retry after an adapter rollback', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('example-order')->save();
    } finally {
        $adapter->rollBack();
    }

    $this->quote->setCustomerNote('First note')->save();
    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()->and($stored->getReservedOrderId())->toBeNull();

    $loaded->setCustomerNote('Second note')->save();

    $stored->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe('example-order')
        ->and($stored->getCustomerNote())->toBe('Second note');
});

it('preserves a newer cart state after a direct adapter commit', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('first-reservation')->save();
        $adapter->commit();
    } catch (Throwable $exception) {
        $adapter->rollBack();
        throw $exception;
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()->and($stored->getReservedOrderId())->toBe('first-reservation');
    $stored->getResource()->save($stored->setIsActive()->setReservedOrderId(null));

    $loaded->setCustomerNote('Example note')->save();

    $stored->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($stored->getCustomerNote())->toBe('Example note');
});

it('preserves a newer cart state between two saves of another copy in one transaction', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('first-reservation')->save();
        $this->quote->setIsActive()->setReservedOrderId(null)->save();
        $loaded->setCustomerNote('Example note')->save();
        $adapter->commit();
    } catch (Throwable $exception) {
        $adapter->rollBack();
        throw $exception;
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($stored->getCustomerNote())->toBe('Example note');
});

it('retries an explicit reopen with the loaded values after rolling back multiple saves', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $this->quote->setIsActive(false)->setReservedOrderId('stored-reservation')->save();
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('first-reservation')->save();
        $loaded->setIsActive()->setReservedOrderId(null)->save();
    } finally {
        $adapter->rollBack();
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()->and($stored->getReservedOrderId())->toBe('stored-reservation');

    $loaded->setCustomerNote('Example note')->save();

    $stored->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($stored->getCustomerNote())->toBe('Example note');
});

it('immediately retries the same cart save after an outer adapter rollback', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('example-order')->save();
    } finally {
        $adapter->rollBack();
    }

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull();

    $loaded->save();

    $stored->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeFalse()
        ->and($stored->getReservedOrderId())->toBe('example-order');
});

it('keeps a reload of the same cart separate from an earlier rolled back save', function (): void {
    $quoteId = $this->quote->getId();
    $loaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    try {
        $loaded->setIsActive(false)->setReservedOrderId('example-order')->save();
        $loaded->loadByIdWithoutStore($quoteId);
        expect($loaded->getIsActive())->toBeFalse()
            ->and($loaded->getReservedOrderId())->toBe('example-order');
    } finally {
        $adapter->rollBack();
    }

    $loaded->setCustomerNote('Example note')->save();

    $stored = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
    expect($stored->getIsActive())->toBeTrue()
        ->and($stored->getReservedOrderId())->toBeNull()
        ->and($stored->getCustomerNote())->toBe('Example note');
});
