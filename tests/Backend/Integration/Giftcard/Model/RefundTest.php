<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->savedExtension = Mage::getStoreConfig('giftcard/general/refund_expiration_extension', 1);
    $this->refundCard = Mage::getModel('giftcard/giftcard')
        ->setCode('REFUND-' . bin2hex(random_bytes(8)))
        ->setWebsiteIds([1])
        ->setBalance(100.0)
        ->setExpiresAt(null)
        ->save();
});

afterEach(function (): void {
    Mage::app()->getStore(1)->setConfig('giftcard/general/refund_expiration_extension', $this->savedExtension);
    $card = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    if ($card->getId()) {
        $card->delete();
    }
});

it('adds both refunds when two models loaded the same starting balance', function (): void {
    $first = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $second = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());

    $first->refund(10.0);
    $second->refund(10.0);

    $fresh = Mage::getModel('giftcard/giftcard')->load($this->refundCard->getId());
    $history = $fresh->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)
        ->setOrder('history_id', 'ASC');
    $balances = [];
    foreach ($history as $entry) {
        $balances[] = [(float) $entry->getBalanceBefore(), (float) $entry->getBalanceAfter()];
    }

    expect($fresh->getBalance())->toBe(120.0)
        ->and($second->getBalance())->toBe(120.0)
        ->and($balances)->toBe([[100.0, 110.0], [110.0, 120.0]]);
});

it('reactivates a stored used card when a refund succeeds', function (): void {
    $this->refundCard->setBalance(0.0)->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->save();

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
    $this->refundCard->setBalance(0.0)->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->save();
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
    $card->load($this->refundCard->getId());

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
    $this->refundCard->setBalance(0.0)
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
