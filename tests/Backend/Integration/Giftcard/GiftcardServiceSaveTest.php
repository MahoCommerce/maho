<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Maho_Giftcard_Service_Giftcard::save() decides how a card is saved, and the model has the rule of one card (#1348).
 */

function giftcardServiceSaveCard(float $balance = 25.0): Maho_Giftcard_Model_Giftcard
{
    $giftcard = Mage::getModel('giftcard/giftcard')
        ->setCode('PEST-SAVE-' . strtoupper(uniqid()))
        ->setBalance($balance);
    $giftcard->setWebsiteIds([(int) Mage::app()->getDefaultStoreView()->getWebsiteId()]);

    return Mage::getService('giftcard/giftcard')->save($giftcard);
}

beforeEach(function () {
    $this->cards = [];
});

afterEach(function () {
    foreach ($this->cards as $card) {
        if ($card->getId()) {
            $card->delete();
        }
    }
});

it('refuses a new card without a balance in the model, so that each writer has the rule', function () {
    $giftcard = Mage::getModel('giftcard/giftcard')->setCode('PEST-SAVE-' . strtoupper(uniqid()))->setBalance(0.0);
    $giftcard->setWebsiteIds([(int) Mage::app()->getDefaultStoreView()->getWebsiteId()]);
    $this->cards[] = $giftcard;

    $errors = [];
    try {
        $giftcard->save();
    } catch (Mage_Core_Exception_Input $e) {
        $errors = $e->getErrors();
    }

    expect($errors)->toBe([['field' => 'initial_balance', 'message' => 'Gift card balance must be greater than zero']])
        ->and($giftcard->getId())->toBeNull();
});

it('creates a used card with a balance of zero and an initial balance', function () {
    $giftcard = Mage::getModel('giftcard/giftcard')->setCode('PEST-SAVE-' . strtoupper(uniqid()))
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_USED)->setBalance(0.0)->setInitialBalance(50.0);
    $giftcard->setWebsiteIds([(int) Mage::app()->getDefaultStoreView()->getWebsiteId()]);
    $this->cards[] = $giftcard;

    $giftcard->save();

    expect($giftcard->getId())->not->toBeNull();
});

it('saves a changed balance of an existing card as an adjustment with a history entry', function () {
    $this->cards[] = $created = giftcardServiceSaveCard(25.0);
    /** @var Maho_Giftcard_Service_Giftcard $service */
    $service = Mage::getService('giftcard/giftcard');
    $giftcard = $service->getById((int) $created->getId());

    $service->save($giftcard->setData('balance', 40.0)->setRecipientName('Ann'), 'Raised by a test');

    $stored = $service->getById((int) $created->getId());
    $entry = $stored->getHistoryCollection()->getFirstItem();
    expect((float) $stored->getData('balance'))->toBe(40.0)
        ->and($stored->getRecipientName())->toBe('Ann')
        ->and($entry->getData('action'))->toBe(Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED)
        ->and((float) $entry->getData('balance_before'))->toBe(25.0)
        ->and((float) $entry->getData('balance_after'))->toBe(40.0)
        ->and($entry->getData('comment'))->toBe('Raised by a test');
});

it('writes no adjustment when the balance of an existing card does not change', function () {
    $this->cards[] = $created = giftcardServiceSaveCard(25.0);
    /** @var Maho_Giftcard_Service_Giftcard $service */
    $service = Mage::getService('giftcard/giftcard');
    $giftcard = $service->getById((int) $created->getId());
    $entries = $giftcard->getHistoryCollection()->getSize();

    $service->save($giftcard->setRecipientName('Bob'), 'Not used');

    $stored = $service->getById((int) $created->getId());
    expect($stored->getRecipientName())->toBe('Bob')
        ->and($stored->getHistoryCollection()->getSize())->toBe($entries);
});

it('gives the balance to show in the current currency of the store', function () {
    $this->cards[] = $giftcard = giftcardServiceSaveCard(25.0);
    $store = Mage::app()->getDefaultStoreView();

    $display = Mage::getService('giftcard/giftcard')->getDisplayBalance($giftcard, $store);

    expect($display)->toBe(['balance' => 25.0, 'currency' => $store->getCurrentCurrencyCode()]);
});
