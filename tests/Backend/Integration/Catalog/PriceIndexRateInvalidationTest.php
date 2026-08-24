<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A website's base price is derived from the rate and stored in the price index, so a rate change
 * leaves the index stale. The code is an ISO 4217 "X" code no real currency uses, so a run never
 * disturbs the store's own rates.
 */
const RATE_INVALIDATION_CURRENCY = 'XTR';
const RATE_INVALIDATION_OTHER_CURRENCY = 'XTS';

/**
 * Read back from the table rather than from the indexer singleton, which holds the process object
 * the test itself just wrote to.
 */
function rateInvalidationStatus(): string
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (string) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('index/process'), ['status'])
            ->where('indexer_code = ?', 'catalog_product_price'),
    );
}

function rateInvalidationSetStatus(string $status): void
{
    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price')
        ->changeStatus($status);
}

/** In memory only, so a run that dies leaves the install's own scope and currencies alone. */
function rateInvalidationConfigure(int $priceScope, string $websiteBaseCurrency): void
{
    $website = Mage::app()->getWebsite(1);
    Mage::getConfig()->setNode('websites/' . $website->getCode() . '/catalog/price/scope', $priceScope);
    Mage::getConfig()->setNode(
        'websites/' . $website->getCode() . '/currency/options/base',
        $websiteBaseCurrency,
    );
    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('catalog/price/scope', $priceScope);
        if ((int) $store->getWebsiteId() === (int) $website->getId()) {
            $store->setConfig('currency/options/base', $websiteBaseCurrency);
        }
    }
    $website->unsetData('base_currency_code');
    Mage::getSingleton('eav/config')->clear();
}

beforeEach(function () {
    $this->indexStatus = rateInvalidationStatus();
    $this->rulesDirty = (int) Mage::getModel('catalogrule/flag')->loadSelf()->getState();
});

afterEach(function () {
    // Shared state: left flipped, it decides whether a later test's product save reindexes
    rateInvalidationSetStatus($this->indexStatus);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState($this->rulesDirty)->save();
    rateInvalidationConfigure(0, Mage::app()->getBaseCurrencyCode());
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('directory/currency_rate'),
        ['currency_to IN (?)' => [RATE_INVALIDATION_CURRENCY, RATE_INVALIDATION_OTHER_CURRENCY]],
    );
    Mage_Directory_Model_Resource_Currency::clearRateCache();
});

it('invalidates the price index when a website prices in the changed currency', function () {
    rateInvalidationConfigure(1, RATE_INVALIDATION_CURRENCY);
    rateInvalidationSetStatus(Mage_Index_Model_Process::STATUS_PENDING);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_CURRENCY => 2.0],
    ]);

    expect(rateInvalidationStatus())->toBe(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
});

/*
 * Catalog rule prices are computed from the price the website sells at, so they go stale with the
 * rate too. The rules are flagged for reapplication, which is what the admin already does for a
 * rule edit, rather than reapplied on the spot inside a rate import.
 */
it('flags the catalog rules for reapplication when a website prices in the changed currency', function () {
    rateInvalidationConfigure(1, RATE_INVALIDATION_CURRENCY);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_CURRENCY => 2.0],
    ]);

    expect((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(1);
});

it('leaves the catalog rules alone for a currency no website prices in', function () {
    rateInvalidationConfigure(1, RATE_INVALIDATION_CURRENCY);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_OTHER_CURRENCY => 2.0],
    ]);

    expect((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(0);
});

/*
 * A scheduled import stores every pair a service offers. Flagging the whole catalog for a currency
 * nothing prices in would leave a permanent "reindex required" banner on an install that has
 * nothing to recompute.
 */
it('leaves the price index alone for a currency no website prices in', function () {
    rateInvalidationConfigure(1, Mage::app()->getBaseCurrencyCode());
    rateInvalidationSetStatus(Mage_Index_Model_Process::STATUS_PENDING);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_CURRENCY => 2.0],
    ]);

    expect(rateInvalidationStatus())->toBe(Mage_Index_Model_Process::STATUS_PENDING);
});

it('leaves the price index alone while prices are global', function () {
    rateInvalidationConfigure(0, RATE_INVALIDATION_CURRENCY);
    rateInvalidationSetStatus(Mage_Index_Model_Process::STATUS_PENDING);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_CURRENCY => 2.0],
    ]);

    expect(rateInvalidationStatus())->toBe(Mage_Index_Model_Process::STATUS_PENDING);
});

/*
 * The rates grid posts every cell it renders. A blank one is not stored, so the currency it names
 * did not move and the catalog that prices in it has nothing to recompute.
 */
it('leaves the price index alone for a blank cell saved beside a stored pair', function () {
    rateInvalidationConfigure(1, RATE_INVALIDATION_CURRENCY);
    rateInvalidationSetStatus(Mage_Index_Model_Process::STATUS_PENDING);

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [
            RATE_INVALIDATION_OTHER_CURRENCY => 2.0,
            RATE_INVALIDATION_CURRENCY => '',
        ],
    ]);

    expect(rateInvalidationStatus())->toBe(Mage_Index_Model_Process::STATUS_PENDING);
});

it('leaves the price index alone when no rate is stored', function () {
    rateInvalidationSetStatus(Mage_Index_Model_Process::STATUS_PENDING);

    // A blank cell stores no pair, so saveRates() announces nothing
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [RATE_INVALIDATION_CURRENCY => ''],
    ]);

    expect(rateInvalidationStatus())->toBe(Mage_Index_Model_Process::STATUS_PENDING);
});

/*
 * The rate is only half of a derived price: the website's base currency picks which rate. Changing
 * that currency in the configuration changes every derived price on the website, so the price
 * indexer has to treat the setting as one of its own, like the price scope.
 */
it('matches a change of a website base currency as a price index event', function () {
    $configData = Mage::getModel('core/config_data')
        ->setPath(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE)
        ->setScope('websites')
        ->setScopeId(1)
        ->setValue(RATE_INVALIDATION_CURRENCY)
        ->setOldValue(Mage::app()->getBaseCurrencyCode());

    $event = Mage::getModel('index/event')
        ->setEntity(Mage_Core_Model_Config_Data::ENTITY)
        ->setType(Mage_Index_Model_Event::TYPE_SAVE)
        ->setDataObject($configData);

    expect(Mage::getModel('catalog/product_indexer_price')->matchEvent($event))->toBeTrue();
});
