<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function giftcardRefundCard(float $balance = 100.0): Maho_Giftcard_Model_Giftcard
{
    return Mage::getModel('giftcard/giftcard')
        ->setCode('REFUND-' . bin2hex(random_bytes(8)))
        ->setWebsiteIds([1])
        ->setBalance($balance)
        ->setExpiresAt(null)
        ->save();
}

beforeEach(function (): void {
    $this->savedExtension = Mage::getStoreConfig('giftcard/general/refund_expiration_extension', 1);
    $this->refundCard = giftcardRefundCard();
});

afterEach(function (): void {
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', $this->savedExtension);
    $card = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    if ($card->getId()) {
        $card->delete();
    }
});

function giftcardRefundHistoryFailure(int $id): Maho_Giftcard_Model_Giftcard
{
    $card = new class extends Maho_Giftcard_Model_Giftcard {
        #[\Override]
        protected function _addHistory(
            string $action,
            float $baseAmount,
            float $baseBalanceBefore,
            float $baseBalanceAfter,
            ?int $orderId = null,
            ?string $comment = null,
        ): void {
            throw new RuntimeException('Test history failure');
        }
    };
    return $card->load($id);
}

it('adds both refunds when two models loaded the same starting balance', function (): void {
    $first = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $second = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());

    $first->refund(10.0);
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('giftcard/history'),
        ['created_at' => '2000-01-01 00:00:00'],
        ['giftcard_id = ?' => $first->getId()],
    );
    $second->refund(10.0);

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $history = $fresh->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
        ->unshiftOrder('history_id', 'ASC');
    $balances = [];
    foreach ($history as $entry) {
        $balances[] = [(float) $entry->getBalanceBefore(), (float) $entry->getBalanceAfter()];
    }

    expect($fresh->getBalance())->toBe(120.0)
        ->and($second->getBalance())->toBe(120.0)
        ->and($balances)->toBe([[100.0, 110.0], [110.0, 120.0]]);
});

it('reactivates a stored used card when a refund succeeds', function (): void {
    $this->refundCard->delete();
    $this->refundCard = giftcardRefundCard(0.0);
    $this->refundCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->save();

    $this->refundCard->refund(25.0);

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    expect($fresh->getBalance())->toBe(25.0)
        ->and($fresh->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->and($this->refundCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->and($fresh->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(1);
});

it('uses the stored status instead of a stale status when refunding', function (): void {
    $stale = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $this->refundCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED)->save();
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', '0');

    $stale->refund(10.0, null, null, 1);

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    expect($fresh->getBalance())->toBe(110.0)
        ->and($fresh->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
});

it('rolls back the balance and reactivation when history cannot be written', function (): void {
    $this->refundCard->delete();
    $this->refundCard = giftcardRefundCard(0.0);
    $this->refundCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->save();
    $card = giftcardRefundHistoryFailure($this->refundCard->getId());

    expect(fn() => $card->refund(25.0))->toThrow(RuntimeException::class, 'Test history failure');

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    expect($fresh->getBalance())->toBe(0.0)
        ->and($fresh->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED)
        ->and($fresh->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(0);
});

it('extends the expiry date of a card that expires within the refund extension', function (): void {
    $soon = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('+5 days');
    $this->refundCard->setExpiresAt($soon->format(Mage_Core_Model_Locale::DATETIME_FORMAT))->save();
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', '30');

    $this->refundCard->refund(10.0, null, 'Goodwill', 1);

    $expiresAt = new DateTimeImmutable($this->refundCard->getExpiresAt(), new DateTimeZone('UTC'));
    $comment = $this->refundCard->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
        ->getFirstItem()
        ->getComment();
    expect($expiresAt > new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('+29 days'))->toBeTrue()
        ->and($comment)->toBe('Goodwill (expiration extended to 30 days from now)');
});

it('reactivates an expired card only when the refund extension is above 0', function (string $extension, string $status): void {
    $past = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('-5 days');
    $this->refundCard->delete();
    $this->refundCard = giftcardRefundCard(0.0);
    $this->refundCard
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED)
        ->setExpiresAt($past->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
        ->save();
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', $extension);

    $this->refundCard->refund(10.0, null, null, 1);

    expect($this->refundCard->getStatus())->toBe($status)
        ->and($this->refundCard->getBalance())->toBe(10.0);
})->with([
    'extension of 30 days' => ['30', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE],
    'no extension' => ['0', Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED],
]);
it('uses the resource writer for the credit, history, reload and rollback', function (bool $historyFails): void {
    $core = Mage::getSingleton('core/resource');
    $write = $core->createUnsharedConnection('core_write');
    $originalCardResource = Mage::getResourceSingleton('giftcard/giftcard');
    $originalHistoryResource = Mage::getResourceSingleton('giftcard/history');
    $cardResource = new class ($write) extends Maho_Giftcard_Model_Resource_Giftcard {
        public function __construct(Maho\Db\Adapter\AdapterInterface $write)
        {
            parent::__construct();
            $this->_connections['write'] = $write;
        }
    };
    $historyResource = new class ($write) extends Maho_Giftcard_Model_Resource_History {
        public function __construct(Maho\Db\Adapter\AdapterInterface $write)
        {
            parent::__construct();
            $this->_connections['write'] = $write;
        }
    };
    Mage::unregister('_resource_singleton/giftcard/giftcard');
    Mage::register('_resource_singleton/giftcard/giftcard', $cardResource);
    Mage::unregister('_resource_singleton/giftcard/history');
    Mage::register('_resource_singleton/giftcard/history', $historyResource);

    try {
        expect($cardResource->getReadConnection())->not->toBe($write);
        if ($historyFails) {
            expect(fn() => giftcardRefundHistoryFailure($this->refundCard->getId())->refund(25.0))
                ->toThrow(RuntimeException::class, 'Test history failure');
            expect(Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId())->getBalance())->toBe(100.0)
                ->and($write->getTransactionLevel())->toBe(0);
            return;
        }
        $card = new class extends Maho_Giftcard_Model_Giftcard {
            public ?Maho\Db\Adapter\AdapterInterface $reloadConnection = null;

            #[\Override]
            protected function _beforeLoad($id, $field = null): self
            {
                $this->reloadConnection = $this->getResource()->getReadConnection();
                return parent::_beforeLoad($id, $field);
            }
        };
        $card->setData($this->refundCard->getData());
        $card->refund(10.0);

        expect($card->reloadConnection)->toBe($write)
            ->and($card->getBalance())->toBe(110.0)
            ->and($card->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(1);
    } finally {
        Mage::unregister('_resource_singleton/giftcard/giftcard');
        Mage::register('_resource_singleton/giftcard/giftcard', $originalCardResource);
        Mage::unregister('_resource_singleton/giftcard/history');
        Mage::register('_resource_singleton/giftcard/history', $originalHistoryResource);
        $write->closeConnection();
    }
})->with([
    'reload and history' => [false],
    'history failure' => [true],
]);

it('rolls back a failed refund while the caller can commit its own transaction', function (): void {
    $this->refundCard->delete();
    $this->refundCard = giftcardRefundCard(0.0);
    $this->refundCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->save();
    $card = giftcardRefundHistoryFailure($this->refundCard->getId());
    $resource = Mage::getResourceSingleton('giftcard/giftcard');
    $resource->beginTransaction();
    $write = $resource->getReadConnection();

    try {
        $write->update($resource->getMainTable(), ['message' => 'Outer transaction'], ['giftcard_id = ?' => $card->getId()]);
        expect(fn() => $card->refund(25.0))->toThrow(RuntimeException::class, 'Test history failure');
        expect($write->getTransactionLevel())->toBe(1);
        $resource->commit();

        $fresh = Mage::getModel('giftcard/giftcard')->load($card->getId());
        expect($fresh->getBalance())->toBe(0.0)
            ->and($fresh->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED)
            ->and($fresh->getMessage())->toBe('Outer transaction')
            ->and($fresh->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(0);
    } finally {
        if ($write->getTransactionLevel() > 0) {
            $resource->rollBack();
        }
    }
});

it('leaves a successful refund inside the caller transaction until its commit', function (): void {
    $resource = Mage::getResourceSingleton('giftcard/giftcard');
    $resource->beginTransaction();
    $write = $resource->getReadConnection();

    try {
        $this->refundCard->refund(10.0);
        expect($write->getTransactionLevel())->toBe(1)
            ->and($this->refundCard->getBalance())->toBe(110.0);
        $resource->rollBack();

        $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
        expect($fresh->getBalance())->toBe(100.0)
            ->and($fresh->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(0);
    } finally {
        if ($write->getTransactionLevel() > 0) {
            $resource->rollBack();
        }
    }
});

it('refuses a refund after the stored card has been deleted', function (): void {
    $stale = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $this->refundCard->delete();

    expect(fn() => $stale->refund(10.0))->toThrow(Mage_Core_Exception::class);
});

it('rejects an invalid refund amount without changing the balance or history', function (float $amount): void {
    expect(fn() => $this->refundCard->refund($amount))->toThrow(Mage_Core_Exception::class);

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    expect($fresh->getBalance())->toBe(100.0)
        ->and($fresh->getHistoryCollection()->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->getSize())->toBe(0);
})->with([
    'zero' => [0.0],
    'negative' => [-1.0],
    'not a number' => [NAN],
    'positive infinity' => [INF],
    'negative infinity' => [-INF],
]);

it('reads the refund extension of the given store, not of the current store', function (): void {
    $past = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify('-5 days');
    $this->refundCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED)
        ->setExpiresAt($past->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
        ->save();
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', '0');
    $currentStore = Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    try {
        $this->refundCard->refund(10.0, null, null, 1);
    } finally {
        Mage::app()->setCurrentStore($currentStore);
    }

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    expect($fresh->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED)
        ->and($fresh->getBalance())->toBe(110.0);
});
