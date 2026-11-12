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

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function giftcardAdminSave(Maho_Giftcard_Model_Giftcard $card, float $balance, float $shownBalance): array
{
    return Tests\Helpers\TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
        'id' => $card->getId(), 'giftcard_id' => $card->getId(), 'code' => $card->getCode(),
        'status' => Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE, 'website_ids' => [1],
        'balance' => $balance, 'shown_balance' => $shownBalance, 'recipient_name' => 'Ann',
    ]);
}

describe('the admin edit form', function (): void {
    beforeEach(function (): void {
        Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
        $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $session->start();
        Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    });

    it('keeps a refund when the form posts the balance that it showed', function (): void {
        $admin = giftcardAdminSave($this->card, 100.0, 100.0);

        $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
        expect($admin['errors'])->toBe([])
            ->and((float) $stored->getBalance())->toBe(110.0)
            ->and($stored->getRecipientName())->toBe('Ann');
    });

    it('refuses a new balance when a refund changed the balance that the form showed', function (): void {
        $admin = giftcardAdminSave($this->card, 150.0, 100.0);

        $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
        expect($admin['errors'])->toHaveCount(1)
            ->and($admin['errors'][0])->toContain('The balance of this gift card changed to')
            ->and((float) $stored->getBalance())->toBe(110.0)
            ->and($stored->getRecipientName())->not->toBe('Ann');
    });

    it('saves a new balance and the other fields together when the shown balance is the stored one', function (): void {
        $admin = giftcardAdminSave($this->card, 150.0, 110.0);

        $stored = Mage::getModel('giftcard/giftcard')->load($this->card->getId());
        expect($admin['errors'])->toBe([])
            ->and((float) $stored->getBalance())->toBe(150.0)
            ->and($stored->getRecipientName())->toBe('Ann');
    });
});
