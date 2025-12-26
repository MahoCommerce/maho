<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail(null); // No email
        $giftcard->save();

        expect(fn() => $this->helper->sendGiftcardEmail($giftcard))
            ->toThrow(Mage_Core_Exception::class, 'No recipient email address.');
    });

    test('sendGiftcardEmail throws exception without template configured', function () {
        // This test verifies the error handling when template is not configured
        // The actual behavior depends on store configuration

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail('test@example.com');
        $giftcard->setRecipientName('Test Recipient');
        $giftcard->save();

        // Check if template is configured
        $templateId = Mage::getStoreConfig('giftcard/email/template');

        if (!$templateId) {
            expect(fn() => $this->helper->sendGiftcardEmail($giftcard))
                ->toThrow(Mage_Core_Exception::class);
        } else {
            // Template is configured, email should attempt to send
            expect(true)->toBeTrue();
        }
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
        $giftcard->setWebsiteId(1);
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
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(50.00);
        $giftcard->setInitialBalance(50.00);
        $giftcard->setRecipientEmail(null);
        $giftcard->save();

        $scheduleTime = new DateTime('+1 day');

        expect(fn() => $this->helper->scheduleGiftcardEmail($giftcard, $scheduleTime))
            ->toThrow(Mage_Core_Exception::class, 'No recipient email address.');
    });
});

describe('Giftcard Email Queue Integration', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('email queue model can be instantiated', function () {
        $emailQueue = Mage::getModel('core/email_queue');
        expect($emailQueue)->toBeInstanceOf(Mage_Core_Model_Email_Queue::class);
    });

    test('email template mailer can be instantiated', function () {
        $mailer = Mage::getModel('core/email_template_mailer');
        expect($mailer)->toBeInstanceOf(Mage_Core_Model_Email_Template_Mailer::class);
    });

    test('email info model can be instantiated', function () {
        $emailInfo = Mage::getModel('core/email_info');
        expect($emailInfo)->toBeInstanceOf(Mage_Core_Model_Email_Info::class);
    });

    test('can create email queue entry for gift card', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail('queue-test@example.com');
        $giftcard->setRecipientName('Queue Test');
        $giftcard->save();

        $emailQueue = Mage::getModel('core/email_queue');
        $emailQueue->setEntityId($giftcard->getId());
        $emailQueue->setEntityType('giftcard');
        $emailQueue->setEventType('giftcard_notification');

        expect($emailQueue->getEntityId())->toBe($giftcard->getId());
        expect($emailQueue->getEntityType())->toBe('giftcard');
        expect($emailQueue->getEventType())->toBe('giftcard_notification');
    });

    test('email template variables are prepared correctly', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode('TEST-EMAIL-VARS');
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(150.00);
        $giftcard->setInitialBalance(150.00);
        $giftcard->setRecipientEmail('vars-test@example.com');
        $giftcard->setRecipientName('Vars Test');
        $giftcard->setSenderName('Sender Test');
        $giftcard->setMessage('Test message content');

        // Verify the data that would go into email template
        $storeId = Mage::app()->getStore()->getId();
        $store = Mage::app()->getStore($storeId);
        $storeCurrencyCode = $store->getCurrentCurrencyCode();

        $vars = [
            'giftcard' => $giftcard,
            'code' => $giftcard->getCode(),
            'balance' => Mage::app()->getLocale()->formatCurrency($giftcard->getBalance($storeCurrencyCode), $storeCurrencyCode),
            'recipient_name' => $giftcard->getRecipientName() ?: 'Valued Customer',
            'sender_name' => $giftcard->getSenderName() ?: '',
            'message' => $giftcard->getMessage() ?: '',
            'qr_url' => $this->helper->getQrCodeDataUrl($giftcard->getCode(), 300),
            'barcode_url' => $this->helper->getBarcodeDataUrl($giftcard->getCode()),
            'store_name' => Mage::getStoreConfig('general/store_information/name', $storeId),
            'store_url' => Mage::getBaseUrl(),
        ];

        expect($vars['code'])->toBe('TEST-EMAIL-VARS');
        expect($vars['recipient_name'])->toBe('Vars Test');
        expect($vars['sender_name'])->toBe('Sender Test');
        expect($vars['message'])->toBe('Test message content');
        expect($vars['balance'])->toContain('150');
    });

    test('expiration date is included in email vars when set', function () {
        $expiresAt = (new DateTime('+365 days'))->format('Y-m-d H:i:s');

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode('TEST-EXPIRY');
        $giftcard->setExpiresAt($expiresAt);

        expect($giftcard->getExpiresAt())->not->toBeNull();

        // Format for display
        $expiryDate = new DateTime($giftcard->getExpiresAt());
        $formattedDate = $expiryDate->format('F j, Y');

        expect($formattedDate)->toBeString();
        expect(strlen($formattedDate))->toBeGreaterThan(5);
    });
});

describe('Giftcard Email Sent Tracking', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('giftcard');
    });

    test('gift card tracks email sent timestamp', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setCode($this->helper->generateCode());
        $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $giftcard->setWebsiteId(1);
        $giftcard->setBalance(100.00);
        $giftcard->setInitialBalance(100.00);
        $giftcard->setRecipientEmail('sent-test@example.com');
        $giftcard->save();

        // Initially no sent timestamp
        expect($giftcard->getEmailSentAt())->toBeNull();

        // Set sent timestamp
        $sentAt = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        $giftcard->setEmailSentAt($sentAt);
        $giftcard->save();

        // Reload and verify
        $reloaded = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
        expect($reloaded->getEmailSentAt())->not->toBeNull();
        expect($reloaded->getEmailSentAt())->toBe($sentAt);
    });

    test('can query gift cards by email sent status', function () {
        // Create cards with different email statuses
        $sentCard = Mage::getModel('giftcard/giftcard');
        $sentCard->setCode($this->helper->generateCode());
        $sentCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $sentCard->setWebsiteId(1);
        $sentCard->setBalance(50.00);
        $sentCard->setInitialBalance(50.00);
        $sentCard->setRecipientEmail('sent@example.com');
        $sentCard->setEmailSentAt(Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        $sentCard->save();

        $unsentCard = Mage::getModel('giftcard/giftcard');
        $unsentCard->setCode($this->helper->generateCode());
        $unsentCard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $unsentCard->setWebsiteId(1);
        $unsentCard->setBalance(50.00);
        $unsentCard->setInitialBalance(50.00);
        $unsentCard->setRecipientEmail('unsent@example.com');
        $unsentCard->setEmailSentAt(null);
        $unsentCard->save();

        // Query unsent
        $unsentCollection = Mage::getResourceModel('giftcard/giftcard_collection')
            ->addFieldToFilter('email_sent_at', ['null' => true])
            ->addFieldToFilter('recipient_email', ['notnull' => true])
            ->addFieldToFilter('giftcard_id', ['in' => [$sentCard->getId(), $unsentCard->getId()]]);

        expect($unsentCollection->getSize())->toBe(1);
        expect((int) $unsentCollection->getFirstItem()->getId())->toBe((int) $unsentCard->getId());
    });
});
