<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The Sales 2.0.3 and Catalog 2.0.3 upgrades remove the recurring profiles. The test puts back the
 * state of a store before the upgrade, runs the scripts, and reads the database.
 */

const RECURRING_REMOVAL_GROUP = 'Recurring Profile';

function recurringRemovalRunUpgrade(): void
{
    $salesSetup = Mage::getResourceModel('sales/setup', 'sales_setup');
    $salesScript = Mage::getModuleDir('sql', 'Mage_Sales') . '/sales_setup/upgrade-2.0.2-2.0.3.php';
    (function () use ($salesScript): void {
        include $salesScript;
    })->call($salesSetup);

    $catalogSetup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $catalogScript = Mage::getModuleDir('data', 'Mage_Catalog') . '/catalog_setup/data-upgrade-2.0.2-2.0.3.php';
    (function () use ($catalogScript): void {
        include $catalogScript;
    })->call($catalogSetup);
}

function recurringRemovalAttributeCodes(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('eav/attribute'), ['attribute_code'])
            ->where('entity_type_id = ?', (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId())
            ->where('attribute_code IN (?)', ['is_recurring', 'recurring_profile', 'recurring_removal_kept'])
            ->order('attribute_code'),
    );
}

function recurringRemovalGroupCount(): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('eav/attribute_group'), [new Maho\Db\Expr('COUNT(*)')])
            ->where('attribute_group_name = ?', RECURRING_REMOVAL_GROUP),
    );
}

function recurringRemovalCleanUp(Mage_Catalog_Model_Resource_Setup $setup): void
{
    $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, 'recurring_removal_kept');
    $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, 'is_recurring');
    $setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, 'recurring_profile');
    $adapter = $setup->getConnection();
    $adapter->delete($setup->getTable('eav/attribute_group'), ['attribute_group_name = ?' => RECURRING_REMOVAL_GROUP]);
    $adapter->dropTable($setup->getTable('sales_recurring_profile_order'));
    $adapter->dropTable($setup->getTable('sales_recurring_profile'));
    $orderItemTable = $setup->getTable('sales/order_item');
    if ($adapter->tableColumnExists($orderItemTable, 'is_nominal')) {
        $adapter->dropColumn($orderItemTable, 'is_nominal');
    }
}

beforeEach(function () {
    $this->setup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $this->adapter = $this->setup->getConnection();
    recurringRemovalCleanUp($this->setup);

    foreach (['sales_recurring_profile' => 'profile_id', 'sales_recurring_profile_order' => 'link_id'] as $name => $idColumn) {
        $this->adapter->createTable(
            $this->adapter->newTable($this->setup->getTable($name))
                ->addColumn($idColumn, Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ], 'ID'),
        );
    }
    $this->adapter->addColumn($this->setup->getTable('sales/order_item'), 'is_nominal', [
        'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'nullable' => false,
        'default' => '0',
        'comment' => 'Is Nominal',
    ]);

    $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'is_recurring', [
        'type' => 'int',
        'label' => 'Enable Recurring Profile',
        'input' => 'select',
        'source' => 'eav/entity_attribute_source_boolean',
        'required' => false,
        'group' => RECURRING_REMOVAL_GROUP,
    ]);
    $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'recurring_profile', [
        'type' => 'text',
        'label' => 'Recurring Payment Profile',
        'input' => 'text',
        'required' => false,
        'group' => RECURRING_REMOVAL_GROUP,
    ]);
});

afterEach(function () {
    recurringRemovalCleanUp($this->setup);
});

it('starts from the state of a store before the upgrade', function () {
    expect($this->adapter->isTableExists($this->setup->getTable('sales_recurring_profile')))->toBeTrue()
        ->and($this->adapter->isTableExists($this->setup->getTable('sales_recurring_profile_order')))->toBeTrue()
        ->and($this->adapter->tableColumnExists($this->setup->getTable('sales/order_item'), 'is_nominal'))->toBeTrue()
        ->and(recurringRemovalAttributeCodes())->toBe(['is_recurring', 'recurring_profile'])
        ->and(recurringRemovalGroupCount())->toBeGreaterThan(0);
});

it('drops the recurring profile tables', function () {
    recurringRemovalRunUpgrade();

    expect($this->adapter->isTableExists($this->setup->getTable('sales_recurring_profile')))->toBeFalse()
        ->and($this->adapter->isTableExists($this->setup->getTable('sales_recurring_profile_order')))->toBeFalse();
});

it('drops the is_nominal column of the order items', function () {
    recurringRemovalRunUpgrade();

    expect($this->adapter->tableColumnExists($this->setup->getTable('sales/order_item'), 'is_nominal'))->toBeFalse();
});

it('removes the recurring profile attributes and their empty group', function () {
    recurringRemovalRunUpgrade();

    expect(recurringRemovalAttributeCodes())->toBe([])
        ->and(recurringRemovalGroupCount())->toBe(0);
});

it('keeps the group when it holds another attribute', function () {
    $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'recurring_removal_kept', [
        'type' => 'varchar',
        'label' => 'Kept',
        'input' => 'text',
        'required' => false,
        'group' => RECURRING_REMOVAL_GROUP,
    ]);

    recurringRemovalRunUpgrade();

    expect(recurringRemovalAttributeCodes())->toBe(['recurring_removal_kept'])
        ->and(recurringRemovalGroupCount())->toBeGreaterThan(0);
});

it('runs a second time without errors', function () {
    recurringRemovalRunUpgrade();
    recurringRemovalRunUpgrade();

    expect($this->adapter->isTableExists($this->setup->getTable('sales_recurring_profile')))->toBeFalse()
        ->and(recurringRemovalAttributeCodes())->toBe([]);
});
