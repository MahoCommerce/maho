<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

test('a new category saves its tree columns when a migrated database lacks their EAV rows', function () {
    $treeColumns = ['path', 'position', 'level', 'children_count'];
    $resetEavState = function (): void {
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_resource_singleton/catalog/category');
    };
    $transaction = Mage::getResourceModel('catalog/category');
    $write = Mage::getSingleton('core/resource')->getConnection('catalog_write');
    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getId();
    $countTreeRows = fn(): int => (int) $write->fetchOne(
        $write->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('eav/attribute'), ['count' => new Maho\Db\Expr('COUNT(*)')])
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('attribute_code IN (?)', $treeColumns),
    );
    $rootId = (int) Mage::app()->getDefaultStoreView()->getRootCategoryId();
    $rootPath = (string) Mage::getModel('catalog/category')->load($rootId)->getPath();
    $treeRowsBefore = $countTreeRows();

    // Roll back through a resource model: it also drops the commit callbacks that the save queues.
    $transaction->beginTransaction();
    try {
        $write->delete(Mage::getSingleton('core/resource')->getTableName('eav/attribute'), [
            'entity_type_id = ?' => $entityTypeId,
            'attribute_code IN (?)' => $treeColumns,
        ]);
        $resetEavState();

        $category = Mage::getModel('catalog/category');
        $category->setStoreId(0)
            ->setName('Category without tree EAV rows')
            ->setIsActive(1)
            ->setPath($rootPath)
            ->setAttributeSetId($category->getDefaultAttributeSetId())
            ->save();

        $row = $write->fetchRow(
            $write->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('catalog/category'), ['path', 'level', 'children_count'])
                ->where('entity_id = ?', $category->getId()),
        );
    } finally {
        $transaction->rollBack();
        $resetEavState();
    }

    expect($countTreeRows())->toBe($treeRowsBefore);
    expect($row['path'])->toBe($rootPath . '/' . $category->getId());
    expect((int) $row['level'])->toBe(count(explode('/', $rootPath)));
    expect((int) $row['children_count'])->toBe(0);
});
