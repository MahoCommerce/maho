<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function currencyRateCount(): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (int) $adapter->fetchOne(
        $adapter->select()->from($resource->getTableName('directory/currency_rate'), ['count' => new Maho\Db\Expr('COUNT(*)')]),
    );
}

describe('scheduled currency rates update', function () {
    beforeEach(function () {
        $this->store = Mage::app()->getStore();
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_ENABLE, '1');
        $this->store->setConfig(Mage_Directory_Model_Observer::CRON_STRING_PATH, '0 0 * * *');

        // Leave no service for the fallback chain to reach, so nothing here hits the network.
        foreach (array_keys(Mage::helper('directory')->getCurrencyImportServices(false)) as $code) {
            $this->store->setConfig("currency/{$code}/active", '0');
        }

        $this->ratesBefore = currencyRateCount();
    });

    it('survives a configured service that no longer exists', function () {
        // A service can disappear from under a store: a module is removed, or a shipped one is
        // retired. Reaching the assertion is half the point, the job used to die right here.
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, 'nosuchservice');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(currencyRateCount())->toBe($this->ratesBefore);
    });

    it('survives having no service configured at all', function () {
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, '');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(currencyRateCount())->toBe($this->ratesBefore);
    });

    it('does nothing at all while scheduled import is disabled', function () {
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_ENABLE, '0');
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, 'nosuchservice');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(currencyRateCount())->toBe($this->ratesBefore);
    });
});
