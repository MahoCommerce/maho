<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * With prices scoped per website, saving a product converts its price into every website's
 * currency. When there is no rate the amount used to be written unconverted under the other
 * currency's label, which is a wrong price a merchant cannot see. There is nothing to write
 * instead, so nothing is written.
 *
 * The second website's base currency is an ISO 4217 "X" code no real currency uses, so no
 * install has a rate for it.
 */
const MISSING_RATE_CODE = 'missing_rate_ws';
const MISSING_RATE_CURRENCY = 'XTN';

function missingRateWebsite(): Mage_Core_Model_Website
{
    /** @var Mage_Core_Model_Website $website */
    $website = Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
    if ($website->getId()) {
        return $website;
    }

    $website = Mage::getModel('core/website')
        ->setCode(MISSING_RATE_CODE)
        ->setName('Missing Rate Website')
        ->setSortOrder(98)
        ->save();

    $group = Mage::getModel('core/store_group')
        ->setWebsiteId((int) $website->getId())
        ->setName('Missing Rate Group')
        ->setRootCategoryId((int) Mage::app()->getStore(1)->getRootCategoryId())
        ->save();

    $store = Mage::getModel('core/store')
        ->setCode(MISSING_RATE_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $group->getId())
        ->setName('Missing Rate Store')
        ->setIsActive(1)
        ->setSortOrder(98)
        ->save();

    $website->setDefaultGroupId((int) $group->getId())->save();
    $group->setDefaultStoreId((int) $store->getId())->save();
    Mage::app()->reinitStores();

    return Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
}

/**
 * Price scope and the second website's currency, in memory only: a test that writes core_config_data
 * would leave the whole install on website price scope if it died mid-run.
 */
function missingRateConfigure(): void
{
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/catalog/price/scope', 1);
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/currency/options/base', MISSING_RATE_CURRENCY);

    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('catalog/price/scope', 1);
    }
    Mage::app()->getStore(MISSING_RATE_CODE)->setConfig('currency/options/base', MISSING_RATE_CURRENCY);
}

function missingRateRestore(): void
{
    Mage::getConfig()->setNode('websites/' . MISSING_RATE_CODE . '/catalog/price/scope', 0);
    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('catalog/price/scope', 0);
    }
}

function missingRateDeleteWebsite(): void
{
    $website = Mage::getModel('core/website')->load(MISSING_RATE_CODE, 'code');
    if (!$website->getId()) {
        return;
    }

    foreach ($website->getStores() as $store) {
        $store->delete();
    }
    foreach ($website->getGroups() as $group) {
        $group->delete();
    }
    $website->delete();
    Mage::app()->reinitStores();
}

beforeEach(function () {
    missingRateWebsite();
    missingRateConfigure();
});

afterEach(function () {
    missingRateRestore();
});

afterAll(function () {
    missingRateDeleteWebsite();
});

it('leaves a website with no rate out of the group price rates', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();

    $prepared = $attribute->getBackend()->preparePriceData(
        [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]],
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        $websiteId,
    );

    expect($prepared)->toBe([]);
});

it('converts the group price for a website that has a rate', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'group_price');
    $websiteId = (int) missingRateWebsite()->getId();

    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [MISSING_RATE_CURRENCY => 2.0],
    ]);

    try {
        $prepared = $attribute->getBackend()->preparePriceData(
            [['website_id' => 0, 'cust_group' => 0, 'price' => 100.0]],
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            $websiteId,
        );

        expect($prepared['0']['price'])->toBe(200.0);
    } finally {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->delete(
            Mage::getSingleton('core/resource')->getTableName('directory/currency_rate'),
            ['currency_to = ?' => MISSING_RATE_CURRENCY],
        );
        Mage_Directory_Model_Resource_Currency::clearRateCache();
    }
});
