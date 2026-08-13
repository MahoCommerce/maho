<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function createActiveCard(): Maho_Giftcard_Model_Giftcard
{
    $card = Mage::getModel('giftcard/giftcard');
    $card->setCode(Mage::helper('giftcard')->generateCode());
    $card->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
    $card->setBalance(50.00);
    $card->setInitialBalance(50.00);
    return $card;
}

describe('Giftcard Website Associations (junction)', function () {
    test('setWebsiteIds persists to the junction and survives reload', function () {
        $card = createActiveCard();
        $card->setWebsiteIds([1]);
        $card->save();

        $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
        expect($reloaded->getWebsiteIds())->toBe([1]);

        $card->delete();
    });

    test('a new card saved without explicit associations defaults to the current website', function () {
        $card = createActiveCard();
        $card->save();

        $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
        expect($reloaded->getWebsiteIds())->toBe([(int) Mage::app()->getStore()->getWebsiteId()]);

        $card->delete();
    });

    test('membership validation follows the junction', function () {
        $card = createActiveCard();
        $card->setWebsiteIds([1]);
        $card->save();

        $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
        expect($reloaded->isValidForWebsite(1))->toBeTrue();
        expect($reloaded->isValidForWebsite(999))->toBeFalse();

        $card->delete();
    });

    test('an explicit empty website set is rejected', function () {
        $card = createActiveCard();
        $card->setData('website_ids', []);

        expect(fn() => $card->save())->toThrow(
            Mage_Core_Exception::class,
            'A gift card must be associated with at least one website.',
        );
    });

    test('a save that did not touch associations leaves the junction alone', function () {
        $card = createActiveCard();
        $card->setWebsiteIds([1]);
        $card->save();

        $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
        // A read followed by an unrelated save must not count as "associations were set"
        expect($reloaded->getWebsiteIds())->toBe([1]);
        $reloaded->setBalance(25.00);
        $reloaded->save();

        $again = Mage::getModel('giftcard/giftcard')->load($card->getId());
        expect($again->getWebsiteIds())->toBe([1]);
        expect((float) $again->getBalance())->toBe(25.00);

        $card->delete();
    });

    test('getWebsite() fails loudly for a card with no associations instead of adopting the current website', function () {
        $card = createActiveCard();
        $card->setWebsiteIds([1]);
        $card->save();

        try {
            // Simulate the orphan a website deletion leaves behind: card row
            // survives, junction rows cascade away
            $write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $write->delete($card->getResource()->getTable('giftcard/website'), ['giftcard_id = ?' => (int) $card->getId()]);

            $orphan = Mage::getModel('giftcard/giftcard')->load($card->getId());
            expect($orphan->getWebsiteIds())->toBe([]);
            expect(fn() => $orphan->getWebsite())->toThrow(
                Mage_Core_Exception::class,
                'Gift card is not associated with any website.',
            );
        } finally {
            $card->delete();
        }
    });

    test('re-saving an unchanged website set does not rewrite the junction', function () {
        $card = createActiveCard();
        $card->setWebsiteIds([1]);
        $card->save();

        try {
            $card->setWebsiteIds([1]);
            $card->save();

            $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
            expect($reloaded->getWebsiteIds())->toBe([1]);
        } finally {
            $card->delete();
        }
    });

    test('websites with different base currencies cannot be associated to one card', function () {
        $website = Mage::getModel('core/website');
        $website->setCode('gc_currency_test_' . uniqid());
        $website->setName('Giftcard Currency Test Website');
        $website->save();

        try {
            // Give the new website a base currency distinct from the default website's
            $code = $website->getCode();
            $baseCurrency = Mage::app()->getBaseCurrencyCode();
            $otherCurrency = $baseCurrency === 'EUR' ? 'USD' : 'EUR';
            Mage::getConfig()->setNode(
                "websites/{$code}/catalog/price/scope",
                (string) Mage_Core_Model_Store::PRICE_SCOPE_WEBSITE,
            );
            Mage::getConfig()->setNode("websites/{$code}/currency/options/base", $otherCurrency);

            $card = createActiveCard();
            $card->setWebsiteIds([1, (int) $website->getId()]);

            expect(fn() => $card->save())->toThrow(
                Mage_Core_Exception::class,
                'A gift card can only be assigned to websites that share the same base currency.',
            );
        } finally {
            $website->delete();
        }
    });

    test('an existing card cannot be re-scoped to websites with a different base currency', function () {
        $website = Mage::getModel('core/website');
        $website->setCode('gc_rescope_test_' . uniqid());
        $website->setName('Giftcard Re-scope Test Website');
        $website->save();

        try {
            $code = $website->getCode();
            $baseCurrency = Mage::app()->getBaseCurrencyCode();
            $otherCurrency = $baseCurrency === 'EUR' ? 'USD' : 'EUR';
            Mage::getConfig()->setNode(
                "websites/{$code}/catalog/price/scope",
                (string) Mage_Core_Model_Store::PRICE_SCOPE_WEBSITE,
            );
            Mage::getConfig()->setNode("websites/{$code}/currency/options/base", $otherCurrency);

            $card = createActiveCard();
            $card->setWebsiteIds([1]);
            $card->save();

            try {
                $card->setWebsiteIds([(int) $website->getId()]);
                expect(fn() => $card->save())->toThrow(
                    Mage_Core_Exception::class,
                    'A gift card cannot be moved to websites with a different base currency.',
                );

                $reloaded = Mage::getModel('giftcard/giftcard')->load($card->getId());
                expect($reloaded->getWebsiteIds())->toBe([1]);
            } finally {
                $card->delete();
            }
        } finally {
            $website->delete();
        }
    });
});

describe('Giftcard 1.0.0 -> 1.1.0 website migration', function () {
    afterEach(function () {
        // A mid-test failure must not leak the legacy column into the shared test database
        $setup = new Mage_Core_Model_Resource_Setup('giftcard_setup');
        $connection = $setup->getConnection();
        $giftcardTable = $setup->getTable('giftcard/giftcard');
        if ($connection->tableColumnExists($giftcardTable, 'website_id')) {
            $connection->dropColumn($giftcardTable, 'website_id');
        }
    });

    test('backfills the junction from the legacy column and drops it', function () {
        $setup = new Mage_Core_Model_Resource_Setup('giftcard_setup');
        $connection = $setup->getConnection();
        $giftcardTable = $setup->getTable('giftcard/giftcard');
        $junctionTable = $setup->getTable('giftcard/website');
        $script = Mage::getModuleDir('sql', 'Maho_Giftcard') . '/giftcard_setup/upgrade-1.0.0-1.1.0.php';
        $runScript = function () use ($setup, $script): void {
            (function (string $file): void {
                include $file;
            })->call($setup, $script);
        };

        // Recreate the pre-1.1.0 shape: scalar column, no junction rows
        $connection->addColumn($giftcardTable, 'website_id', [
            'type' => Maho\Db\Ddl\Table::TYPE_SMALLINT,
            'unsigned' => true,
            'nullable' => true,
            'comment' => 'Legacy single-website association (test fixture)',
        ]);
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $connection->insert($giftcardTable, [
            'code' => 'MIGRATION-TEST-' . strtoupper(uniqid()),
            'status' => Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE,
            'website_id' => 1,
            'balance' => 42.0,
            'initial_balance' => 42.0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $giftcardId = (int) $connection->lastInsertId($giftcardTable, 'giftcard_id');
        $connection->delete($junctionTable, ['giftcard_id = ?' => $giftcardId]);

        try {
            $runScript();

            $select = $connection->select()
                ->from($junctionTable, ['website_id'])
                ->where('giftcard_id = ?', $giftcardId);
            expect(array_map('intval', $connection->fetchCol($select)))->toBe([1]);

            expect($connection->tableColumnExists($giftcardTable, 'website_id'))->toBeFalse();
            $runScript();
            expect(array_map('intval', $connection->fetchCol($select)))->toBe([1]);
        } finally {
            $connection->delete($giftcardTable, ['giftcard_id = ?' => $giftcardId]);
        }
    });
});
