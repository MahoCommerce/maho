<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard Email Helper', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('sendGiftcardEmail throws exception without recipient email', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail(null); // No email
        $giftcard->save();

        expect(fn() => $this->helper->sendGiftcardEmail($giftcard))
            ->toThrow(Mage_Core_Exception::class, 'No recipient email address.');
    });

    test('sendGiftcardEmail throws exception without template configured', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail('test@example.com');
        $giftcard->setRecipientName('Test Recipient');
        $giftcard->save();

        Mage::app()->getStore()->setConfig('giftcard/email/template', '');

        expect(fn() => $this->helper->sendGiftcardEmail($giftcard))
            ->toThrow(Mage_Core_Exception::class, 'No email template configured.');
        expect(Mage::getModel('giftcard/giftcard')->load($giftcard->getId())->getEmailSentAt())->toBeNull();
    });
});

describe('Giftcard Email Scheduling', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('scheduleGiftcardEmail sets scheduled time on gift card', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->setRecipientEmail('schedule@test.com');
        $giftcard->setRecipientName('Scheduled Recipient');
        $giftcard->save();

        $scheduleTime = new DateTime('+2 days', new DateTimeZone('UTC'));

        $result = $this->helper->scheduleGiftcardEmail($giftcard, $scheduleTime);

        expect($result)->toBeTrue();

        // Reload and verify
        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect($reloaded->getEmailScheduledAt())->not->toBeNull();

        $scheduledAt = new DateTime($reloaded->getEmailScheduledAt());
        expect($scheduledAt->format('Y-m-d'))->toBe($scheduleTime->format('Y-m-d'));
    });

    test('scheduleGiftcardEmail throws exception without recipient email', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteIds([1]);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->setRecipientEmail(null);
        $giftcard->save();

        $scheduleTime = new DateTime('+1 day');

        expect(fn() => $this->helper->scheduleGiftcardEmail($giftcard, $scheduleTime))
            ->toThrow(Mage_Core_Exception::class, 'No recipient email address.');
    });
});
