<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Import button of System > Manage Currency > Rates and the scheduled import fetch the rates through
 * Mage_Directory_Service_Currency::fetchRates() (#1348). Each test gives the same import service to both transports.
 */

const CURRENCY_PARITY_SERVICE = 'currencyparity';

/**
 * The import model of the test service. It gives one rate for each pair, and a warning when $warning is set.
 */
class CurrencyParity_Model_Import extends Mage_Directory_Model_Currency_Import_Abstract
{
    public static ?string $warning = null;

    #[\Override]
    protected function _convert($currencyFrom, $currencyTo)
    {
        if (self::$warning !== null) {
            $this->_messages[] = self::$warning;
            return null;
        }
        return 1.2345;
    }
}

class CurrencyParityObserver extends Mage_Directory_Model_Observer
{
    /** @var list<string> */
    public array $warnings = [];

    #[\Override]
    protected function _sendImportWarnings(array $importWarnings): void
    {
        $this->warnings = array_values($importWarnings);
    }
}

/**
 * @return array{messages: array{success: list<string>, errors: list<string>}, warnings: list<string>, rates: mixed}
 */
function currencyParityAdmin(string $service): array
{
    $session = Mage::getSingleton('adminhtml/session');
    $session->unsRates();
    $session->getMessages(true);
    $request = new Mage_Core_Controller_Request_Http(
        Symfony\Component\HttpFoundation\Request::create('http://localhost/admin/system_currency/fetchRates', 'POST', ['rate_services' => $service]),
    );
    Mage::app()->setRequest($request);
    try {
        new Mage_Adminhtml_System_CurrencyController($request, new Mage_Core_Controller_Response_Http())->fetchRatesAction();
    } catch (Throwable) {
        // The trailing redirect builds an admin url outside a dispatched request. The session holds the outcome
    }

    $warnings = [];
    foreach ($session->getMessages()->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::WARNING) {
            $warnings[] = $message->getText();
        }
    }
    return ['messages' => AdminApiParity::adminMessages(), 'warnings' => $warnings, 'rates' => $session->getRates()];
}

function currencyParityCron(): CurrencyParityObserver
{
    $observer = new CurrencyParityObserver();
    $observer->scheduledUpdateCurrencyRates();
    return $observer;
}

/**
 * @return array<string, array<string, float>> the stored rates, by base currency and by currency
 */
function currencyParityStoredRates(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $rates = [];
    foreach ($adapter->fetchAll($adapter->select()->from($resource->getTableName('directory/currency_rate'))) as $row) {
        $rates[$row['currency_from']][$row['currency_to']] = (float) $row['rate'];
    }
    return $rates;
}

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();

    Mage::getConfig()->setNode('global/models/currencyparity/class', 'CurrencyParity_Model');
    Mage::getConfig()->setNode('global/currency/import/services/' . CURRENCY_PARITY_SERVICE . '/name', 'Parity');
    Mage::getConfig()->setNode('global/currency/import/services/' . CURRENCY_PARITY_SERVICE . '/model', 'currencyparity/import');
    CurrencyParity_Model_Import::$warning = null;

    $this->store = Mage::app()->getStore();
    $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_ENABLE, '1');
    $this->store->setConfig(Mage_Directory_Model_Observer::CRON_STRING_PATH, '0 0 * * *');
    $this->store->setConfig(Mage_Directory_Model_Observer::IMPORT_SERVICE, CURRENCY_PARITY_SERVICE);
    // No other service, so the fallback chain of the cron never reaches the network
    foreach (array_keys(Mage::helper('directory')->getCurrencyImportServices(false)) as $code) {
        $this->store->setConfig("currency/{$code}/active", $code === CURRENCY_PARITY_SERVICE ? '1' : '0');
    }
});

afterEach(function (): void {
    $this->connection->rollBack();
    Mage::getConfig()->setNode('global/currency/import/services/' . CURRENCY_PARITY_SERVICE . '/model', '');
    $this->store->resetConfig();
    Mage_Directory_Model_Resource_Currency::clearRateCache();
});

it('fetches the same rates from the import service in the admin and in the cron', function (): void {
    $admin = currencyParityAdmin(CURRENCY_PARITY_SERVICE);
    $cron = currencyParityCron();
    $stored = currencyParityStoredRates();

    expect($admin['messages']['errors'])->toBe([])
        ->and($admin['warnings'])->toBe([])
        ->and($admin['rates'])->not->toBeEmpty()
        ->and($cron->warnings)->toBe([]);
    foreach ($admin['rates'] as $from => $rates) {
        foreach ($rates as $to => $rate) {
            expect($stored[$from][$to])->toBe((float) $rate);
        }
    }
});

it('reports the warning of the import service in the admin and in the cron, and the cron saves no rate', function (): void {
    if (count(Mage::getModel('directory/currency')->getConfigAllowCurrencies()) < 2) {
        $this->markTestSkipped('The store allows one currency, so the import service converts nothing.');
    }
    CurrencyParity_Model_Import::$warning = 'No rate for this pair.';
    $before = currencyParityStoredRates();

    $admin = currencyParityAdmin(CURRENCY_PARITY_SERVICE);
    $cron = currencyParityCron();

    expect($admin['warnings'])->toContain('No rate for this pair.')
        ->and($cron->warnings)->toContain('Parity: No rate for this pair.')
        ->and(currencyParityStoredRates())->toBe($before);
});

it('refuses an import service that is not enabled in the admin, and the cron saves no rate', function (): void {
    $this->store->setConfig('currency/' . CURRENCY_PARITY_SERVICE . '/active', '0');
    $before = currencyParityStoredRates();
    $message = Mage::helper('directory')->__('Invalid Import Service specified.');

    $admin = currencyParityAdmin(CURRENCY_PARITY_SERVICE);
    $cron = currencyParityCron();

    expect($admin['messages']['errors'])->toBe([$message])
        ->and($admin['rates'])->toBeNull()
        ->and($cron->warnings)->toHaveCount(1)
        ->and($cron->warnings[0])->toContain($message)
        ->and(currencyParityStoredRates())->toBe($before);
});
