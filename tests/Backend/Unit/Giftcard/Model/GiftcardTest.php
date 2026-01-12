<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard Model Constants', function () {
    test('has correct status constants', function () {
        expect(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)->toBe('active');
        expect(Maho_Giftcard_Model_Giftcard::STATUS_USED)->toBe('used');
        expect(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED)->toBe('expired');
        expect(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED)->toBe('disabled');
    });

    test('has correct action constants', function () {
        expect(Maho_Giftcard_Model_Giftcard::ACTION_CREATED)->toBe('created');
        expect(Maho_Giftcard_Model_Giftcard::ACTION_USED)->toBe('used');
        expect(Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED)->toBe('refunded');
        expect(Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)->toBe('adjusted');
        expect(Maho_Giftcard_Model_Giftcard::ACTION_EXPIRED)->toBe('expired');
    });
});

describe('Giftcard Model Instantiation', function () {
    test('can be instantiated via Mage factory', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        expect($giftcard)->toBeInstanceOf(Maho_Giftcard_Model_Giftcard::class);
    });

    test('resource model is properly configured', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        expect($giftcard->getResource())->toBeInstanceOf(Maho_Giftcard_Model_Resource_Giftcard::class);
    });

    test('collection can be instantiated', function () {
        $collection = Mage::getResourceModel('giftcard/giftcard_collection');
        expect($collection)->toBeInstanceOf(Maho_Giftcard_Model_Resource_Giftcard_Collection::class);
    });
});

describe('Giftcard isValid() Logic', function () {
    beforeEach(function () {
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setWebsiteId(1);
    });

    test('returns false when status is not active', function () {
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
        $this->giftcard->setBalance(100.00);
        expect($this->giftcard->isValid())->toBeFalse();

        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED);
        expect($this->giftcard->isValid())->toBeFalse();

        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
        expect($this->giftcard->isValid())->toBeFalse();
    });

    test('returns false when balance is zero or negative', function () {
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setBalance(0);
        expect($this->giftcard->isValid())->toBeFalse();

        $this->giftcard->setBalance(-10.00);
        expect($this->giftcard->isValid())->toBeFalse();
    });

    test('returns true when active with positive balance and no expiration', function () {
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setBalance(50.00);
        $this->giftcard->setExpiresAt(null);
        expect($this->giftcard->isValid())->toBeTrue();
    });

    test('returns true when expiration date is in future', function () {
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setBalance(50.00);
        $futureDate = (new DateTime('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->giftcard->setExpiresAt($futureDate);
        expect($this->giftcard->isValid())->toBeTrue();
    });
});

describe('Giftcard isValidForWebsite() Logic', function () {
    beforeEach(function () {
        $this->giftcard = Mage::getModel('giftcard/giftcard');
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        $this->giftcard->setBalance(100.00);
        $this->giftcard->setWebsiteId(1);
    });

    test('returns true for matching website', function () {
        expect($this->giftcard->isValidForWebsite(1))->toBeTrue();
    });

    test('returns false for different website', function () {
        expect($this->giftcard->isValidForWebsite(2))->toBeFalse();
        expect($this->giftcard->isValidForWebsite(999))->toBeFalse();
    });

    test('returns false if card is not valid regardless of website', function () {
        $this->giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED);
        expect($this->giftcard->isValidForWebsite(1))->toBeFalse();
    });
});

describe('Giftcard Balance Getter', function () {
    test('returns raw balance when no currency specified', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setData('balance', 123.45);
        expect($giftcard->getBalance())->toBe(123.45);
    });

    test('returns balance as float', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setData('balance', '99.99');
        expect($giftcard->getBalance())->toBeFloat();
        expect($giftcard->getBalance())->toBe(99.99);
    });
});

describe('Giftcard History Collection', function () {
    test('returns history collection instance', function () {
        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setId(1);
        $collection = $giftcard->getHistoryCollection();
        expect($collection)->toBeInstanceOf(Maho_Giftcard_Model_Resource_History_Collection::class);
    });
});
