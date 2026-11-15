<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The first save of a gift card writes its "created" history entry in the model (#1348). So a card of the admin, of
 * the API and of a purchase has the same first entry, and the purchase observer writes no second one.
 */

/**
 * @return list<array{0: string, 1: float, 2: float, 3: float, 4: ?string}> the action, the amount, the balance
 *                                                                          before, the balance after and the comment
 */
function giftcardCreatedHistoryRows(Maho_Giftcard_Model_Giftcard $giftcard): array
{
    $rows = [];
    foreach ($giftcard->getHistoryCollection() as $entry) {
        $rows[] = [
            (string) $entry->getAction(),
            (float) $entry->getBaseAmount(),
            (float) $entry->getBalanceBefore(),
            (float) $entry->getBalanceAfter(),
            $entry->getComment(),
        ];
    }
    return $rows;
}

beforeEach(function (): void {
    $this->cards = [];
});

afterEach(function (): void {
    foreach ($this->cards as $card) {
        $card->delete();
    }
});

it('writes one "created" entry for a card that the service saves, and none at the next save', function (): void {
    $giftcard = Mage::getModel('giftcard/giftcard')->setInitialBalance(40.0)->setWebsiteIds([1]);
    $this->cards[] = Mage::getService('giftcard/giftcard')->save($giftcard);
    $afterCreate = giftcardCreatedHistoryRows($giftcard);

    $giftcard->setRecipientName('Pest Recipient');
    Mage::getService('giftcard/giftcard')->save($giftcard);

    expect($afterCreate)->toBe([[Maho_Giftcard_Model_Giftcard::ACTION_CREATED, 40.0, 0.0, 40.0, null]])
        ->and(giftcardCreatedHistoryRows($giftcard))->toBe($afterCreate);
});

it('writes the comment that the caller gives for the "created" entry', function (): void {
    $giftcard = Mage::getModel('giftcard/giftcard')->setInitialBalance(25.0)->setWebsiteIds([1])
        ->setCreationComment('Created from order #100000001');
    $giftcard->save();
    $this->cards[] = $giftcard;

    expect(giftcardCreatedHistoryRows($giftcard))
        ->toBe([[Maho_Giftcard_Model_Giftcard::ACTION_CREATED, 25.0, 0.0, 25.0, 'Created from order #100000001']]);
});
