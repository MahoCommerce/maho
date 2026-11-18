<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

test('a new category saves its tree columns when a migrated database lacks their static EAV rows', function (string $change) {
    $treeColumns = ['path', 'position', 'level', 'children_count'];
    $resetEavState = function (): void {
        Mage::getSingleton('eav/config')->clear();
        Mage::unregister('_resource_singleton/catalog/category');
    };
    $categoryResource = Mage::getResourceModel('catalog/category');
    $write = Mage::getSingleton('core/resource')->getConnection('catalog_write');
    $eavAttributeTable = Mage::getSingleton('core/resource')->getTableName('eav/attribute');
    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY)->getId();
    $treeRows = ['entity_type_id = ?' => $entityTypeId, 'attribute_code IN (?)' => $treeColumns];
    $countStaticTreeRows = fn(): int => (int) $write->fetchOne(
        $write->select()
            ->from($eavAttributeTable, ['count' => new Maho\Db\Expr('COUNT(*)')])
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('attribute_code IN (?)', $treeColumns)
            ->where('backend_type = ?', 'static'),
    );
    $rootId = (int) Mage::app()->getDefaultStoreView()->getRootCategoryId();
    $rootPath = (string) Mage::getModel('catalog/category')->load($rootId)->getPath();
    $staticTreeRowsBefore = $countStaticTreeRows();

    // Roll back through a resource model: it also drops the commit callbacks that the save queues.
    $categoryResource->beginTransaction();
    try {
        match ($change) {
            'delete' => $write->delete($eavAttributeTable, $treeRows),
            'not static' => $write->update($eavAttributeTable, ['backend_type' => 'int'], $treeRows),
        };
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
        $categoryResource->rollBack();
        $resetEavState();
    }

    expect($countStaticTreeRows())->toBe($staticTreeRowsBefore);
    expect($row['path'])->toBe($rootPath . '/' . $category->getId());
    expect((int) $row['level'])->toBe(count(explode('/', $rootPath)));
    expect((int) $row['children_count'])->toBe(0);
})->with(['delete', 'not static']);
