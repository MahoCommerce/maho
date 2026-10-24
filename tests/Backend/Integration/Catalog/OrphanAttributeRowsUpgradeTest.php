<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The upgrade to 2.0.2 removed attributes after startSetup(), which turns off the foreign keys on
 * SQLite and PostgreSQL, so the rows of those attributes stayed. The test deletes an attribute with
 * the foreign keys off, runs the 2.0.3 script, and reads the database.
 */

const ORPHAN_ROWS_TABLES = ['eav/entity_attribute', 'catalog/eav_attribute', ['catalog/product', 'varchar']];

function orphanRowsRunUpgrade(): void
{
    $script = Mage::getModuleDir('data', 'Mage_Catalog') . '/catalog_setup/data-upgrade-2.0.2-2.0.3.php';
    (function () use ($script): void {
        include $script;
    })->call(Mage::getResourceModel('catalog/setup', 'catalog_setup'));
}

function orphanRowsForeignKeys(Maho\Db\Adapter\Pdo\Mysql|Maho\Db\Adapter\Pdo\Pgsql|Maho\Db\Adapter\Pdo\Sqlite $adapter, bool $on): void
{
    $adapter->raw_query(match (true) {
        $adapter instanceof Maho\Db\Adapter\Pdo\Mysql => 'SET FOREIGN_KEY_CHECKS = ' . (int) $on,
        $adapter instanceof Maho\Db\Adapter\Pdo\Pgsql => 'SET session_replication_role = ' . ($on ? 'origin' : 'replica'),
        default => 'PRAGMA foreign_keys = ' . ($on ? 'ON' : 'OFF'),
    });
}

function orphanRowsCount(Mage_Catalog_Model_Resource_Setup $setup, string|array $table, int $attributeId): int
{
    $adapter = $setup->getConnection();

    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($setup->getTable($table), [new Maho\Db\Expr('COUNT(*)')])
            ->where('attribute_id = ?', $attributeId),
    );
}

beforeEach(function () {
    $this->setup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $adapter = $this->setup->getConnection();
    $entityTypeId = (int) $this->setup->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);

    $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'orphan_rows_test', [
        'type' => 'varchar',
        'label' => 'Orphan Rows Test',
        'input' => 'text',
        'required' => false,
        'group' => 'General',
    ]);
    $this->attributeId = (int) $this->setup->getAttributeId($entityTypeId, 'orphan_rows_test');
    $productId = (int) $adapter->fetchOne(
        $adapter->select()->from($this->setup->getTable('catalog/product'), ['entity_id'])->order('entity_id')->limit(1),
    );
    $adapter->insert($this->setup->getTable(['catalog/product', 'varchar']), [
        'entity_type_id' => $entityTypeId,
        'attribute_id' => $this->attributeId,
        'store_id' => 0,
        'entity_id' => $productId,
        'value' => 'orphan',
    ]);
    $this->nameAttributeId = (int) $this->setup->getAttributeId($entityTypeId, 'name');
    $this->nameRows = orphanRowsCount($this->setup, ['catalog/product', 'varchar'], $this->nameAttributeId);

    orphanRowsForeignKeys($adapter, false);
    try {
        $adapter->delete($this->setup->getTable('eav/attribute'), ['attribute_id = ?' => $this->attributeId]);
    } finally {
        orphanRowsForeignKeys($adapter, true);
    }
});

afterEach(function () {
    $adapter = $this->setup->getConnection();
    foreach (ORPHAN_ROWS_TABLES as $table) {
        $adapter->delete($this->setup->getTable($table), ['attribute_id = ?' => $this->attributeId]);
    }
});

it('starts with the rows of an attribute that does not exist', function () {
    foreach (ORPHAN_ROWS_TABLES as $table) {
        expect(orphanRowsCount($this->setup, $table, $this->attributeId))->toBeGreaterThan(0);
    }
});

it('deletes the rows of an attribute that does not exist', function () {
    orphanRowsRunUpgrade();

    foreach (ORPHAN_ROWS_TABLES as $table) {
        expect(orphanRowsCount($this->setup, $table, $this->attributeId))->toBe(0);
    }
});

it('keeps the rows of an attribute that exists', function () {
    orphanRowsRunUpgrade();

    expect(orphanRowsCount($this->setup, ['catalog/product', 'varchar'], $this->nameAttributeId))->toBe($this->nameRows);
});
