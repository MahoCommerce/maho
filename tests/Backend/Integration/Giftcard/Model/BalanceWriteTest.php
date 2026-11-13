<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->card = Mage::getModel('giftcard/giftcard')
        ->setCode('BALANCE-' . bin2hex(random_bytes(8)))
        ->setWebsiteIds([1])
        ->setBalance(100.0)
        ->setExpiresAt(null)
        ->save();
    $this->stale = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
    Mage::getModel('giftcard/giftcard')->load($this->card->getId())->refund(10.0);
});

afterEach(function (): void {
    $card = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
    if ($card->getId()) {
        $card->delete();
    }
});

function giftcardStoredBalance(int $id): float
{
    return (float) Mage::getModel('giftcard/giftcard')->load($id)->getBalance();
}

it('keeps a refund when a card that was loaded before it is saved', function (): void {
    $this->stale->setMessage('Changed after the refund')->save();

    expect(giftcardStoredBalance($this->card->getId()))->toBe(110.0);
});

it('keeps a refund when a status change saves a card that was loaded before it', function (): void {
    $this->stale->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED)->save();

    $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
    expect((float) $stored->getBalance())->toBe(110.0)
        ->and($stored->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
});

it('records an adjustment from the stored balance, not from the loaded one', function (): void {
    $this->stale->adjustBalance(150.0, 'Admin bonus');

    $history = $this->stale->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)
        ->getFirstItem();
    expect(giftcardStoredBalance($this->card->getId()))->toBe(150.0)
        ->and((float) $this->stale->getBalance())->toBe(150.0)
        ->and((float) $history->getBalanceBefore())->toBe(110.0)
        ->and((float) $history->getBaseAmount())->toBe(40.0);
});

it('refuses an adjustment when the balance changed after the person saw it', function (): void {
    expect(fn() => $this->stale->adjustBalance(150.0, 'Admin bonus', 100.0))
        ->toThrow(Mage_Core_Exception_Conflict::class);

    $adjustments = $this->stale->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)
        ->getSize();
    expect(giftcardStoredBalance($this->card->getId()))->toBe(110.0)
        ->and($adjustments)->toBe(0);
});

it('accepts an adjustment when the balance is the one that the person saw', function (): void {
    $this->stale->adjustBalance(150.0, 'Admin bonus', 110.0);

    expect(giftcardStoredBalance($this->card->getId()))->toBe(150.0);
});

it('reactivates a used card from the stored status', function (): void {
    Mage::getModel('giftcard/giftcard')->load($this->card->getId())->use(110.0);

    $this->stale->adjustBalance(20.0);

    $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
    expect($stored->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->and((float) $stored->getBalance())->toBe(20.0);
});

it('refuses to adjust a card that no longer exists', function (): void {
    Mage::getModel('giftcard/giftcard')->load($this->card->getId())->delete();

    expect(fn() => $this->stale->adjustBalance(150.0))
        ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
});

it('refuses an amount that is not a finite number on each balance write, and changes nothing', function (float $amount): void {
    $entries = $this->stale->getHistoryCollection()->getSize();

    expect(fn() => $this->stale->use($amount))->toThrow(Mage_Core_Exception::class, 'Amount must be greater than zero.')
        ->and(fn() => $this->stale->useUpTo($amount))->toThrow(Mage_Core_Exception::class, 'Amount must be greater than zero.')
        ->and(fn() => $this->stale->adjustBalance($amount))->toThrow(Mage_Core_Exception_Input::class, 'Gift card balance must be a finite number');

    $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
    expect((float) $stored->getBalance())->toBe(110.0)
        ->and($stored->getHistoryCollection()->getSize())->toBe($entries);
})->with([
    'not a number' => [NAN],
    'positive infinity' => [INF],
    'negative infinity' => [-INF],
]);

it('writes no history when the new balance is the stored balance', function (): void {
    $this->stale->adjustBalance(110.0, 'No change');

    $adjustments = $this->stale->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)
        ->getSize();
    expect($adjustments)->toBe(0)
        ->and((float) $this->stale->getBalance())->toBe(110.0);
});

it('adjusts through the service when the new balance is the loaded one but not the stored one', function (): void {
    Mage::getService('giftcard/giftcard')->adjustBalance($this->stale, 100.0, 'Back to the loaded balance');

    $history = $this->stale->getHistoryCollection()
        ->addFieldToFilter('action', Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)
        ->getFirstItem();
    expect(giftcardStoredBalance($this->card->getId()))->toBe(100.0)
        ->and((float) $history->getBalanceBefore())->toBe(110.0)
        ->and((float) $history->getBaseAmount())->toBe(-10.0);
});
