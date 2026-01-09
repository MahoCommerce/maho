<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Giftcard History Model', function () {
    test('can be instantiated via Mage factory', function () {
        $history = Mage::getModel('giftcard/history');
        expect($history)->toBeInstanceOf(Maho_Giftcard_Model_History::class);
    });

    test('resource model is properly configured', function () {
        $history = Mage::getModel('giftcard/history');
        expect($history->getResource())->toBeInstanceOf(Maho_Giftcard_Model_Resource_History::class);
    });

    test('collection can be instantiated', function () {
        $collection = Mage::getResourceModel('giftcard/history_collection');
        expect($collection)->toBeInstanceOf(Maho_Giftcard_Model_Resource_History_Collection::class);
    });

    test('can set and get all history fields', function () {
        $history = Mage::getModel('giftcard/history');

        $history->setGiftcardId(123);
        $history->setAction(Maho_Giftcard_Model_Giftcard::ACTION_USED);
        $history->setBaseAmount(-50.00);
        $history->setBalanceBefore(100.00);
        $history->setBalanceAfter(50.00);
        $history->setOrderId(456);
        $history->setComment('Test usage');
        $history->setAdminUserId(1);

        expect($history->getGiftcardId())->toBe(123);
        expect($history->getAction())->toBe('used');
        expect((float) $history->getBaseAmount())->toBe(-50.00);
        expect((float) $history->getBalanceBefore())->toBe(100.00);
        expect((float) $history->getBalanceAfter())->toBe(50.00);
        expect($history->getOrderId())->toBe(456);
        expect($history->getComment())->toBe('Test usage');
        expect($history->getAdminUserId())->toBe(1);
    });
});
