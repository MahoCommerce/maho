<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard CRUD Operations', function () {
    test('can create and save gift card to database', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientName('Test Recipient');
        $giftcard->setRecipientEmail('recipient@test.com');
        $giftcard->setSenderName('Test Sender');
        $giftcard->setSenderEmail('sender@test.com');
        $giftcard->setMessage('Test message');
        $giftcard->save();

        expect($giftcard->getId())->toBeGreaterThan(0);
        expect($giftcard->getCreatedAt())->not->toBeNull();
        expect($giftcard->getUpdatedAt())->not->toBeNull();
    });

    test('can load gift card by ID', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(75.50);
        $giftcard->setInitialBalance(75.50);
        $giftcard->save();

        $loadedCard = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());

        expect((int) $loadedCard->getId())->toBe((int) $giftcard->getId());
        expect($loadedCard->getCode())->toBe($code);
        expect((float) $loadedCard->getBalance())->toBe(75.50);
        expect($loadedCard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });

    test('can load gift card by code', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(200.00);
        $giftcard->setInitialBalance(200.00);
        $giftcard->save();

        $loadedCard = Mage::getModel('giftcard/giftcard')->loadByCode($code);

        expect((int) $loadedCard->getId())->toBe((int) $giftcard->getId());
        expect((float) $loadedCard->getBalance())->toBe(200.00);
    });

    test('loadByCode returns empty model for non-existent code', function () {
        $loadedCard = Mage::getModel('giftcard/giftcard')->loadByCode('NONEXISTENT-CODE-12345');

        expect($loadedCard->getId())->toBeNull();
    });

    test('can update gift card', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setMessage('Original message');
        $giftcard->save();

        $id = $giftcard->getId();

        // Update
        $giftcard->setMessage('Updated message');
        $giftcard->setBalance(50.00);
        $giftcard->save();

        // Reload and verify
        $reloaded = Mage::getModel('giftcard/giftcard')->load($id);
        expect($reloaded->getMessage())->toBe('Updated message');
        expect((float) $reloaded->getBalance())->toBe(50.00);
        expect((float) $reloaded->getInitialBalance())->toBe(100.00); // Unchanged
    });

    test('can delete gift card', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $id = $giftcard->getId();
        $giftcard->delete();

        $deletedCard = Mage::getModel('giftcard/giftcard')->load($id);
        expect($deletedCard->getId())->toBeNull();
    });
});

describe('Giftcard Balance Operations with Database', function () {
    test('use() deducts balance and creates history', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->use(30.00, null, 'Test usage');

        // Reload to verify persistence
        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(70.00);
        expect($reloaded->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Check history
        $history = $reloaded->getHistoryCollection();
        expect($history->getSize())->toBeGreaterThanOrEqual(1);

        $lastEntry = $history->getFirstItem();
        expect($lastEntry->getAction())->toBe(Maho_Giftcard_Model_Giftcard::ACTION_USED);
        expect((float) $lastEntry->getBaseAmount())->toBe(-30.00);
        expect((float) $lastEntry->getBalanceBefore())->toBe(100.00);
        expect((float) $lastEntry->getBalanceAfter())->toBe(70.00);
    });

    test('use() transitions to USED status when balance reaches zero', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->save();

        $giftcard->use(50.00);

        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(0.00);
        expect($reloaded->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED);
    });

    test('use() throws exception when amount exceeds balance', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(25.00);
        $giftcard->setInitialBalance(25.00);
        $giftcard->save();

        expect(fn() => $giftcard->use(50.00))
            ->toThrow(Mage_Core_Exception::class, 'Amount exceeds gift card balance.');
    });

    test('use() throws exception for invalid card', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        expect(fn() => $giftcard->use(10.00))
            ->toThrow(Mage_Core_Exception::class, 'This gift card is not valid for use.');
    });

    test('use() throws exception for zero or negative amount', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        expect(fn() => $giftcard->use(0))
            ->toThrow(Mage_Core_Exception::class, 'Amount must be greater than zero.');

        expect(fn() => $giftcard->use(-10.00))
            ->toThrow(Mage_Core_Exception::class, 'Amount must be greater than zero.');
    });

    test('refund() adds balance and creates history', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->refund(50.00, null, 'Test refund');

        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(50.00);
        expect($reloaded->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);

        // Check history
        $history = $reloaded->getHistoryCollection();
        $lastEntry = $history->getFirstItem();
        expect($lastEntry->getAction())->toBe(Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED);
        expect((float) $lastEntry->getBaseAmount())->toBe(50.00);
    });

    test('refund() reactivates USED card', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED);

        $giftcard->refund(25.00);

        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });

    test('adjustBalance() works for admin adjustments', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->adjustBalance(150.00, 'Admin bonus');

        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect((float) $reloaded->getBalance())->toBe(150.00);

        // Check history
        $history = $reloaded->getHistoryCollection();
        $lastEntry = $history->getFirstItem();
        expect($lastEntry->getAction())->toBe(Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED);
        expect((float) $lastEntry->getBaseAmount())->toBe(50.00); // 150 - 100
        expect($lastEntry->getComment())->toBe('Admin bonus');
    });

    test('adjustBalance() to zero sets USED status', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->adjustBalance(0.00, 'Set to zero');

        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_USED);
    });

    test('adjustBalance() from zero reactivates card', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(0.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->adjustBalance(75.00, 'Restore balance');

        expect($giftcard->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    });
});

describe('Giftcard Expiration Validation', function () {
    test('isValid() returns false for expired card and updates status', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        // Create card with past expiration
        $pastDate = (new DateTime('-1 day', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($pastDate);
        $giftcard->save();

        // isValid should return false and update status
        expect($giftcard->isValid())->toBeFalse();

        // Reload and check status was updated
        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect($reloaded->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
    });

    test('isValid() returns true for card with future expiration', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $futureDate = (new DateTime('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setExpiresAt($futureDate);
        $giftcard->save();

        expect($giftcard->isValid())->toBeTrue();
    });
});

describe('Giftcard Collection Operations', function () {
    test('can filter collection by status', function () {
        $helper = Mage::helper('giftcard');

        // Create cards with different statuses
        $activeCard = Mage::getModel('giftcard/giftcard');
        $activeCard->setCode($helper->generateCode());
        $activeCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $activeCard->setWebsiteId(1);
        $activeCard->setBalance(100.00);
        $activeCard->setInitialBalance(100.00);
        $activeCard->save();

        $usedCard = Mage::getModel('giftcard/giftcard');
        $usedCard->setCode($helper->generateCode());
        $usedCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        $usedCard->setWebsiteId(1);
        $usedCard->setBalance(0.00);
        $usedCard->setInitialBalance(50.00);
        $usedCard->save();

        // Filter by active status
        $activeCollection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('status', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
            ->addFieldToFilter('giftcard_id', ['in' => [$activeCard->getId(), $usedCard->getId()]]);

        expect($activeCollection->getSize())->toBe(1);
        expect((int) $activeCollection->getFirstItem()->getId())->toBe((int) $activeCard->getId());
    });

    test('can filter collection by website', function () {
        $helper = Mage::helper('giftcard');

        $card1 = Mage::getModel('giftcard/giftcard');
        $card1->setCode($helper->generateCode());
        $card1->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $card1->setWebsiteId(1);
        $card1->setBalance(100.00);
        $card1->setInitialBalance(100.00);
        $card1->save();

        $collection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('website_id', 1)
            ->addFieldToFilter('giftcard_id', $card1->getId());

        expect($collection->getSize())->toBe(1);
    });
});

describe('Giftcard History Integration', function () {
    test('history entries are created for all operations', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        // Perform operations (use null for order_id to avoid FK constraint)
        $giftcard->use(30.00, null, 'Order payment');
        $giftcard->refund(10.00, null, 'Partial refund');
        $giftcard->adjustBalance(100.00, 'Admin adjustment');

        // Check history
        $history = $giftcard->getHistoryCollection();
        expect($history->getSize())->toBe(3);

        // Verify order of actions (most recent first)
        $actions = [];
        foreach ($history as $entry) {
            $actions[] = $entry->getAction();
        }

        expect($actions)->toContain(Maho_Giftcard_Model_Giftcard::ACTION_USED);
        expect($actions)->toContain(Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED);
        expect($actions)->toContain(Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED);
    });

    test('history entries have correct timestamps', function () {
        $helper = Mage::helper('giftcard');
        $code = $helper->generateCode();

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($code);
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->save();

        $giftcard->use(10.00);

        $history = $giftcard->getHistoryCollection()->getFirstItem();
        expect($history->getCreatedAt())->not->toBeNull();

        $createdAt = new DateTime($history->getCreatedAt());
        $now = new DateTime();
        $diff = $now->getTimestamp() - $createdAt->getTimestamp();

        // Should be within last minute
        expect($diff)->toBeLessThan(60);
    });
});
