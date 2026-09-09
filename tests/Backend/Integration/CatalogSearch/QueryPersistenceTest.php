<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const QUERY_PERSISTENCE_TOKEN = 'zzpersistence';

function catalogSearchHelper(): Mage_CatalogSearch_Helper_Data
{
    // The verdict is memoized per helper instance, so a new request needs a new instance.
    Mage::unregister('_helper/catalogsearch');

    /** @var Mage_CatalogSearch_Helper_Data $helper */
    $helper = Mage::helper('catalogsearch');
    return $helper;
}

function insertSearchQuery(string $text, array $data = []): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->insert(
        Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'),
        array_merge([
            'query_text' => $text,
            'num_results' => 0,
            'popularity' => 1,
            'store_id' => (int) Mage::app()->getStore()->getId(),
            'updated_at' => (new DateTimeImmutable('-200 days', new DateTimeZone('UTC')))
                ->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
        ], $data),
    );
}

function searchQueryTexts(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query');

    return $adapter->fetchCol(
        $adapter->select()->from($table, 'query_text')->where('query_text LIKE ?', QUERY_PERSISTENCE_TOKEN . '%'),
    );
}

function deleteTestSearchQueries(): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
        Mage::getSingleton('core/resource')->getTableName('catalogsearch/search_query'),
        ['query_text LIKE ?' => QUERY_PERSISTENCE_TOKEN . '%'],
    );
}

beforeEach(function () {
    // A fresh client per test, so each test starts with a full budget.
    $_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
    deleteTestSearchQueries();
});

afterEach(fn() => deleteTestSearchQueries());

describe('Search term persistence budget', function () {
    it('allows the configured number of terms per client, then blocks', function () {
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT, 3);

        for ($i = 0; $i < 3; $i++) {
            expect(catalogSearchHelper()->canLogQuery())->toBeTrue();
        }
        expect(catalogSearchHelper()->canLogQuery())->toBeFalse();
    });

    it('records one hit per request, not one per call', function () {
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT, 1);

        $helper = catalogSearchHelper();
        expect($helper->canLogQuery())->toBeTrue();
        expect($helper->canLogQuery())->toBeTrue();

        expect(catalogSearchHelper()->canLogQuery())->toBeFalse();
    });

    it('never blocks when the limit is zero', function () {
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_RATE_LIMIT, 0);

        for ($i = 0; $i < 50; $i++) {
            expect(catalogSearchHelper()->canLogQuery())->toBeTrue();
        }
    });

    it('searches without a saved query row', function () {
        /** @var Mage_CatalogSearch_Model_Query $query */
        $query = Mage::getModel('catalogsearch/query');
        $query->setQueryText(QUERY_PERSISTENCE_TOKEN . 'term');
        $query->setStoreId((int) Mage::app()->getDefaultStoreView()->getId());

        Mage::app()->getRequest()->setParam('q', QUERY_PERSISTENCE_TOKEN . 'term');
        Mage::unregister('_helper/catalogsearch');
        Mage::getModel('catalogsearch/fulltext')->prepareResult($query);

        expect($query->getId())->toBeEmpty();
        expect(searchQueryTexts())->toBe([]);
    });
});

describe('Search term retention', function () {
    it('deletes only stale terms that found nothing and were searched once', function () {
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-stale');
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-popular', ['popularity' => 5]);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-found', ['num_results' => 3]);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-recent', [
            'updated_at' => Mage_Core_Model_Locale::nowUtc(),
        ]);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-synonym', ['synonym_for' => 'shoes']);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-redirect', ['redirect' => 'https://example.com']);

        /** @var Mage_CatalogSearch_Model_Resource_Query $resource */
        $resource = Mage::getResourceModel('catalogsearch/query');
        expect($resource->cleanOldQueries(90))->toBe(1);

        expect(searchQueryTexts())->not->toContain(QUERY_PERSISTENCE_TOKEN . '-stale');
        expect(searchQueryTexts())->toHaveCount(5);
    });

    it('deletes nothing while the cron job is disabled', function () {
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_CLEAN_ENABLED, 0);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-stale');

        Mage::getModel('catalogsearch/query')->cleanOldQueries();

        expect(searchQueryTexts())->toBe([QUERY_PERSISTENCE_TOKEN . '-stale']);
    });

    it('deletes a stale term when the cron job is enabled', function () {
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_CLEAN_ENABLED, 1);
        Mage::app()->getStore()->setConfig(Mage_CatalogSearch_Model_Query::XML_PATH_LOG_CLEAN_AFTER_DAYS, 90);
        insertSearchQuery(QUERY_PERSISTENCE_TOKEN . '-stale');

        Mage::getModel('catalogsearch/query')->cleanOldQueries();

        expect(searchQueryTexts())->toBe([]);
    });
});
