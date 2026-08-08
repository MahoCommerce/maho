<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Stand-in services registered into the config tree, so the chain can be exercised without
 * touching the network. XTS is the ISO code reserved for testing, so the rates these return
 * cannot collide with a real store's.
 */
class Mage_Directory_Model_Currency_Import_Testgood extends Mage_Directory_Model_Currency_Import_Abstract
{
    public static int $calls = 0;

    protected function _convert($currencyFrom, $currencyTo)
    {
        return 1;
    }

    public function fetchRates()
    {
        self::$calls++;
        $this->_messages = [];
        return ['XTS' => ['XTS' => 1.0]];
    }
}

class Mage_Directory_Model_Currency_Import_Testbad extends Mage_Directory_Model_Currency_Import_Abstract
{
    public static int $calls = 0;

    protected function _convert($currencyFrom, $currencyTo)
    {
        return 1;
    }

    public function fetchRates()
    {
        self::$calls++;
        $this->_messages = ['service is down'];
        return ['XTS' => ['XTS' => null]];
    }
}

function registerFakeService(string $code, string $model, int $sortOrder): void
{
    Mage::getConfig()->setNode("global/currency/import/services/{$code}/name", ucfirst($code));
    Mage::getConfig()->setNode("global/currency/import/services/{$code}/model", $model);
    Mage::app()->getStore()->setConfig("currency/{$code}/active", '1');
    Mage::app()->getStore()->setConfig("currency/{$code}/sort_order", (string) $sortOrder);
}

function testRateExists(): bool
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (bool) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('directory/currency_rate'), ['count' => new Maho\Db\Expr('COUNT(*)')])
            ->where('currency_from = ?', 'XTS'),
    );
}

function deleteTestRates(): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete($resource->getTableName('directory/currency_rate'), ['currency_from = ?' => 'XTS']);
}

describe('currency import fallback', function () {
    beforeEach(function () {
        Mage_Directory_Model_Currency_Import_Testgood::$calls = 0;
        Mage_Directory_Model_Currency_Import_Testbad::$calls = 0;

        deleteTestRates();

        $this->store = Mage::app()->getStore();
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_ENABLE, '1');
        $this->store->setConfig(Mage_Directory_Model_Observer::CRON_STRING_PATH, '0 0 * * *');

        // Keep the real services out of the chain, so nothing here reaches the network.
        foreach (array_keys(Mage::helper('directory')->getCurrencyImportServices(false)) as $code) {
            $this->store->setConfig("currency/{$code}/active", '0');
        }
    });

    afterEach(function () {
        deleteTestRates();
    });

    it('falls back to the next service when the configured one fails', function () {
        registerFakeService('testbad', 'directory/currency_import_testbad', 10);
        registerFakeService('testgood', 'directory/currency_import_testgood', 20);
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, 'testbad');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(Mage_Directory_Model_Currency_Import_Testbad::$calls)->toBe(1);
        expect(Mage_Directory_Model_Currency_Import_Testgood::$calls)->toBe(1);
        expect(testRateExists())->toBeTrue();
    });

    it('stops at the first service that delivers, leaving the rest untouched', function () {
        registerFakeService('testgood', 'directory/currency_import_testgood', 10);
        registerFakeService('testbad', 'directory/currency_import_testbad', 20);
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, 'testgood');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(Mage_Directory_Model_Currency_Import_Testgood::$calls)->toBe(1);
        expect(Mage_Directory_Model_Currency_Import_Testbad::$calls)->toBe(0);
        expect(testRateExists())->toBeTrue();
    });

    it('tries the enabled services in sort order when none is configured', function () {
        registerFakeService('testgood', 'directory/currency_import_testgood', 5);
        registerFakeService('testbad', 'directory/currency_import_testbad', 1);
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, '');

        $chain = array_keys(Mage::helper('directory')->getCurrencyImportServices());
        expect($chain)->toBe(['testbad', 'testgood']);

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(Mage_Directory_Model_Currency_Import_Testbad::$calls)->toBe(1);
        expect(testRateExists())->toBeTrue();
    });

    it('skips services the store has switched off', function () {
        registerFakeService('testgood', 'directory/currency_import_testgood', 10);
        registerFakeService('testbad', 'directory/currency_import_testbad', 20);
        $this->store->setConfig('currency/testgood/active', '0');
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, '');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(Mage_Directory_Model_Currency_Import_Testgood::$calls)->toBe(0);
        expect(Mage_Directory_Model_Currency_Import_Testbad::$calls)->toBe(1);
        expect(testRateExists())->toBeFalse();
    });

    it('writes nothing when every service in the chain fails', function () {
        registerFakeService('testbad', 'directory/currency_import_testbad', 10);
        $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, 'testbad');

        Mage::getModel('directory/observer')->scheduledUpdateCurrencyRates();

        expect(testRateExists())->toBeFalse();
    });
});
