<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

// No startSetup(): it turns off the foreign keys, and the rows of the removed attributes must cascade

$adapter = $installer->getConnection();
$table = $installer->getTable(['catalog/product', 'varchar']);
$entityTypeId = (int) $installer->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);
$isEmpty = fn(?string $value): bool => $value === null || $value === '' || $value === 'no_selection';

// Where the file of $target is empty in a store view, write the file of the first $sources role
// that is not empty there, and its label where the label of $target is empty. A role is a pair of
// a file and a label code. A store view without its own value reads the value of the default scope.
$copyWhereEmpty = function (array $target, array $sources) use ($installer, $adapter, $table, $entityTypeId, $isEmpty): void {
    $attributeIds = fn(array $codes): array => array_map(fn(string $code): int => (int) $installer->getAttributeId($entityTypeId, $code), $codes);
    [$targetId, $targetLabelId] = $attributeIds($target);
    $sourcePairs = array_values(array_filter(array_map($attributeIds, $sources), fn(array $ids): bool => $ids[0] !== 0));
    $sourceIds = array_column($sourcePairs, 0);
    if ($targetId === 0 || $sourceIds === []) {
        return;
    }

    $lastEntityId = 0;
    while (true) {
        $entityIds = $adapter->fetchCol($adapter->select()
            ->distinct()
            ->from($table, ['entity_id'])
            ->where('attribute_id IN (?)', $sourceIds)
            ->where('entity_id > ?', $lastEntityId)
            ->order('entity_id')
            ->limit(1000));
        if ($entityIds === []) {
            break;
        }
        $lastEntityId = (int) array_last($entityIds);

        $values = [];
        $rows = $adapter->fetchAll($adapter->select()
            ->from($table, ['entity_id', 'attribute_id', 'store_id', 'value'])
            ->where('attribute_id IN (?)', [$targetId, $targetLabelId, ...array_merge(...$sourcePairs)])
            ->where('entity_id IN (?)', $entityIds));
        foreach ($rows as $row) {
            $values[(int) $row['entity_id']][(int) $row['attribute_id']][(int) $row['store_id']] = $row['value'];
        }

        $inserts = [];
        foreach ($values as $entityId => $byAttribute) {
            $storeIds = array_unique(array_merge(...array_map(array_keys(...), array_values($byAttribute))));
            sort($storeIds);
            foreach ($storeIds as $storeId) {
                $read = fn(int $attributeId): ?string => $byAttribute[$attributeId][$storeId] ?? $byAttribute[$attributeId][0] ?? null;
                if (!$isEmpty($read($targetId))) {
                    continue;
                }
                foreach ($sourcePairs as [$sourceId, $sourceLabelId]) {
                    $value = $read($sourceId);
                    if (!$isEmpty($value)) {
                        $byAttribute[$targetId][$storeId] = $value;
                        $inserts[] = [
                            'entity_type_id' => $entityTypeId,
                            'attribute_id' => $targetId,
                            'store_id' => $storeId,
                            'entity_id' => $entityId,
                            'value' => $value,
                        ];
                        $label = $read($sourceLabelId);
                        if ($targetLabelId !== 0 && $isEmpty($read($targetLabelId)) && !$isEmpty($label)) {
                            $inserts[] = ['entity_type_id' => $entityTypeId, 'attribute_id' => $targetLabelId, 'store_id' => $storeId, 'entity_id' => $entityId, 'value' => $label];
                        }
                        break;
                    }
                }
            }
        }
        foreach (array_chunk($inserts, 1000) as $chunk) {
            $adapter->insertOnDuplicate($table, $chunk, ['value']);
        }
    }
};

// A product has one image from this version on. The theme slots set the size of each place where
// it shows, so the small_image and thumbnail roles and their labels go, and the listings read image.
// A product whose image is empty keeps the file and the label of its old roles.
$copyWhereEmpty(['image', 'image_label'], [['small_image', 'small_image_label'], ['thumbnail', 'thumbnail_label']]);
foreach (['small_image', 'thumbnail', 'small_image_label', 'thumbnail_label'] as $code) {
    $installer->removeAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
}
$installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'used_in_product_listing', 1);
$installer->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'image', 'frontend_label', 'Main Image');

// A scope without its own placeholder or watermark for image takes the one of small_image, else
// the one of thumbnail. The file field decides, and the other watermark fields follow it.
$configTable = $installer->getTable('core/config_data');
$configGroups = [
    'catalog/placeholder/%s_placeholder' => [],
    'design/watermark/%s_image' => ['design/watermark/%s_imageOpacity', 'design/watermark/%s_size', 'design/watermark/%s_position'],
];
foreach ($configGroups as $filePath => $otherPaths) {
    $rows = $adapter->fetchAll($adapter->select()
        ->from($configTable, ['scope', 'scope_id', 'path', 'value'])
        ->where('path IN (?)', array_map(fn(string $role): string => sprintf($filePath, $role), ['image', 'small_image', 'thumbnail'])));
    $byScope = [];
    foreach ($rows as $row) {
        $byScope["{$row['scope']}/{$row['scope_id']}"][$row['path']] = $row['value'];
    }
    foreach ($byScope as $scopeKey => $values) {
        if (!$isEmpty($values[sprintf($filePath, 'image')] ?? null)) {
            continue;
        }
        foreach (['small_image', 'thumbnail'] as $role) {
            if ($isEmpty($values[sprintf($filePath, $role)] ?? null)) {
                continue;
            }
            [$scope, $scopeId] = explode('/', $scopeKey);
            foreach ([$filePath, ...$otherPaths] as $path) {
                $value = $adapter->fetchOne($adapter->select()
                    ->from($configTable, ['value'])
                    ->where('scope = ?', $scope)
                    ->where('scope_id = ?', (int) $scopeId)
                    ->where('path = ?', sprintf($path, $role)));
                if ($value !== false) {
                    $installer->setConfigData(sprintf($path, 'image'), $value, $scope, (int) $scopeId);
                }
            }
            break;
        }
    }
}

$paths = ['catalog/product_image/base_width', 'catalog/product_image/small_width'];
foreach (['small_image', 'thumbnail'] as $role) {
    $paths[] = "catalog/placeholder/{$role}_placeholder";
    foreach (['image', 'imageOpacity', 'size', 'position'] as $field) {
        $paths[] = "design/watermark/{$role}_{$field}";
    }
}
$adapter->delete($configTable, ['path IN (?)' => $paths]);
