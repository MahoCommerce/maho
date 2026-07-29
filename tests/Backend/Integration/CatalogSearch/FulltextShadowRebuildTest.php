<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * A full rebuild stages into a shadow table and swaps it in, so InnoDB never
 * accumulates the FULLTEXT entries of the rows it replaces.
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only: other backends rebuild in place and have no FULLTEXT bloat.');
    }
});

/** @return array<string, string> index name => index type */
function fulltextIndexes(Mysql $adapter, string $table): array
{
    return $adapter->fetchPairs(
        'SELECT INDEX_NAME, INDEX_TYPE FROM information_schema.STATISTICS'
        . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? GROUP BY INDEX_NAME, INDEX_TYPE',
        [$table],
    );
}

it('swaps in a rebuilt index without changing the table structure', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $resource = Mage::getResourceModel('catalogsearch/fulltext');
    $table = $resource->getMainTable();

    $before = fulltextIndexes($adapter, $table);
    expect($before)->toContain('FULLTEXT');

    $resource->rebuildIndex();

    // Names survive the swap, so ./maho migrate has nothing to rename back.
    expect(fulltextIndexes($adapter, $table))->toBe($before);

    expect($adapter->isTableExists($table . '_shadow'))->toBeFalse();
    expect($adapter->isTableExists($table . '_retired'))->toBeFalse();
});

it('stages the rebuild rather than emptying the live index', function () {
    $resource = Mage::getResourceModel('catalogsearch/fulltext');

    expect($resource)->toBeInstanceOf(Mage_CatalogSearch_Model_Resource_Fulltext::class);

    $canStage = new ReflectionMethod($resource, '_canStageRebuild');
    expect($canStage->invoke($resource))->toBeTrue();
});

it('writes to the live table when no rebuild is staged', function () {
    $engine = Mage::helper('catalogsearch')->getEngine();

    expect($engine->getIndexTable())->toBe($engine->getMainTable());

    $engine->setIndexTable('some_shadow_table');
    expect($engine->getIndexTable())->toBe('some_shadow_table');

    $engine->setIndexTable(null);
    expect($engine->getIndexTable())->toBe($engine->getMainTable());
});
