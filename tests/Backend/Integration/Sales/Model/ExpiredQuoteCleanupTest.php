<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class ExpiredQuoteCleanupTestObserver
{
    public static ?Closure $afterCollectionLoad = null;
    public static array $deletions = [];

    public function afterCollectionLoad(Maho\Event\Observer $observer): void
    {
        $collection = $observer->getCollection();
        $callback = self::$afterCollectionLoad;
        if ($callback !== null && $collection instanceof Mage_Sales_Model_Resource_Quote_Collection) {
            $callback($collection);
        }
    }

    public function recordDeletion(Maho\Event\Observer $observer): void
    {
        self::$deletions[] = [
            $observer->getEvent()->getName(),
            (int) $observer->getQuote()->getId(),
            Mage::getSingleton('core/resource')->getConnection('core_write')->getTransactionLevel(),
        ];
    }
}

beforeEach(function (): void {
    $resource = Mage::getSingleton('core/resource');
    $this->connection = $resource->getConnection('core_write');
    $this->table = $resource->getTableName('sales/quote');
    $this->store = Mage::app()->getDefaultStoreView();
    $this->quoteIds = [];
    $this->createQuote = function (string $updatedAt, bool $active = true): int {
        $this->connection->insert($this->table, [
            'store_id' => (int) $this->store->getId(),
            'is_active' => (int) $active,
            'updated_at' => $updatedAt,
            'customer_note' => 'Sample eligible cart',
        ]);
        return $this->quoteIds[] = (int) $this->connection->lastInsertId($this->table);
    };
    $this->remainingQuotes = fn(): array => array_map(
        intval(...),
        $this->connection->fetchCol($this->connection->select()->from($this->table, 'entity_id')
            ->where('entity_id IN (?)', $this->quoteIds)->order('entity_id')),
    );

    foreach ([
        'core_collection_abstract_load_after' => 'afterCollectionLoad',
        'sales_quote_delete_before' => 'recordDeletion',
        'sales_quote_delete_after' => 'recordDeletion',
        'sales_quote_delete_commit_after' => 'recordDeletion',
    ] as $event => $method) {
        $path = 'global/events/' . $event . '/observers/expired_quote_cleanup_test';
        Mage::getConfig()->setNode($path . '/class', ExpiredQuoteCleanupTestObserver::class);
        Mage::getConfig()->setNode($path . '/method', $method);
    }
});

afterEach(function (): void {
    ExpiredQuoteCleanupTestObserver::$afterCollectionLoad = null;
    ExpiredQuoteCleanupTestObserver::$deletions = [];
    if ($this->quoteIds !== []) {
        $this->connection->delete($this->table, ['entity_id IN (?)' => $this->quoteIds]);
    }
});

it('keeps a cart saved after selection and applies additional filters to the selection', function (): void {
    $expired = ($this->createQuote)('2000-01-01 00:00:00');
    $refreshed = ($this->createQuote)('2000-01-01 00:00:00');
    $protected = ($this->createQuote)('2000-01-01 00:00:00');
    $recent = ($this->createQuote)(Mage::app()->getLocale()->formatDateForDb('now'));
    $this->connection->update($this->table, ['customer_note' => 'Sample protected cart'], ['entity_id = ?' => $protected]);
    ExpiredQuoteCleanupTestObserver::$afterCollectionLoad = static function (Mage_Sales_Model_Resource_Quote_Collection $collection) use ($refreshed): void {
        if (!isset($collection->getItems()[$refreshed])) {
            return;
        }

        ExpiredQuoteCleanupTestObserver::$afterCollectionLoad = null;
        Mage::getModel('sales/quote')->load($refreshed)->setCustomerEmail('refreshed@example.test')->save();
    };

    Mage::getModel('sales/observer')->setExpireQuotesAdditionalFilterFields([
        'entity_id' => ['in' => $this->quoteIds],
        'customer_note' => 'Sample eligible cart',
    ])->cleanExpiredQuotes();

    expect(($this->remainingQuotes)())->toBe([$refreshed, $protected, $recent])
        ->and(ExpiredQuoteCleanupTestObserver::$deletions)->toBe([
            ['sales_quote_delete_before', $expired, 1],
            ['sales_quote_delete_after', $expired, 1],
            ['sales_quote_delete_commit_after', $expired, 0],
        ]);
});

it('applies the zero-day lifetime only to inactive carts and preserves ordinary deletion', function (): void {
    $storeConfig = Mage::getConfig()->getNode('stores')->{$this->store->getCode()};
    $storeConfig->setNode('checkout/cart/delete_quote_after', '0');
    $active = ($this->createQuote)('2000-01-01 00:00:00');
    $inactive = ($this->createQuote)('2000-01-01 00:00:00', false);
    $reactivated = ($this->createQuote)('2000-01-01 00:00:00', false);
    ExpiredQuoteCleanupTestObserver::$afterCollectionLoad = function (Mage_Sales_Model_Resource_Quote_Collection $collection) use ($reactivated): void {
        if (!isset($collection->getItems()[$reactivated])) {
            return;
        }

        ExpiredQuoteCleanupTestObserver::$afterCollectionLoad = null;
        $this->connection->update($this->table, ['is_active' => 1], ['entity_id = ?' => $reactivated]);
    };

    Mage::getModel('sales/observer')->setExpireQuotesAdditionalFilterFields([
        'entity_id' => ['in' => $this->quoteIds],
    ])->cleanExpiredQuotes();
    expect(($this->remainingQuotes)())->toBe([$active, $reactivated]);

    Mage::getModel('sales/quote')->load($active)->delete();
    expect(($this->remainingQuotes)())->toBe([$reactivated])
        ->and(ExpiredQuoteCleanupTestObserver::$deletions)->toBe([
            ['sales_quote_delete_before', $inactive, 1],
            ['sales_quote_delete_after', $inactive, 1],
            ['sales_quote_delete_commit_after', $inactive, 0],
            ['sales_quote_delete_before', $active, 1],
            ['sales_quote_delete_after', $active, 1],
            ['sales_quote_delete_commit_after', $active, 0],
        ]);
});
