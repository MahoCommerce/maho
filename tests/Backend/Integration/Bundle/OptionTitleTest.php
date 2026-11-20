<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('saves a bundle option title in a store view', function (
    bool $hasDefaultRow,
    array $defaultData,
    ?string $expectedDefaultTitle,
): void {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $resources = Mage::getSingleton('core/resource');
    $write = $resources->getConnection('core_write');
    $table = $resources->getTableName('bundle/option_value');

    /** @var Mage_Bundle_Model_Option $option */
    $option = Mage::getModel('bundle/option');
    $resource = $option->getResource();
    $resource->beginTransaction();
    try {
        $attributeSetId = (int) Mage::getSingleton('eav/config')
            ->getEntityType('catalog_product')
            ->getDefaultAttributeSetId();
        $product = Mage::getModel('catalog/product')->setStoreId(0)
            ->setAttributeSetId($attributeSetId)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
            ->setSku('bundle-option-title-' . bin2hex(random_bytes(6)))
            ->setName('Bundle title test')
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
            ->setPrice(0)
            ->save();
        $option->setParentId((int) $product->getId())->setType('select')->setRequired(false)->setPosition(0)
            ->setStoreId(0)->setTitle('Stored default title')->save();
        $optionId = (int) $option->getId();
        if (!$hasDefaultRow) {
            $write->delete($table, ['option_id = ?' => $optionId, 'store_id = ?' => 0]);
        }

        /** @var Mage_Bundle_Model_Option $localized */
        $localized = Mage::getModel('bundle/option')->load($optionId);
        expect($localized->hasData('default_title'))->toBeFalse();
        $localized->addData($defaultData)->setStoreId($storeId)->setTitle('Store view title')->save();

        $select = $write->select()->from($table, ['store_id', 'title'])
            ->where('option_id = ?', $optionId)
            ->order('store_id ASC');
        expect($write->fetchPairs($select))->toBe([
            0 => $expectedDefaultTitle,
            $storeId => 'Store view title',
        ]);
    } finally {
        $resource->rollBack();
    }
})->with([
    'default omitted' => [true, [], 'Stored default title'],
    'new default provided' => [true, ['default_title' => 'New default title'], 'New default title'],
    'default row missing' => [false, [], null],
]);
