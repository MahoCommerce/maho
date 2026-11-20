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
    $productId = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if ($productId === 0) {
        $this->markTestSkipped('The store has no bundle product');
    }

    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $resources = Mage::getSingleton('core/resource');
    $write = $resources->getConnection('core_write');
    $table = $resources->getTableName('bundle/option_value');

    /** @var Mage_Bundle_Model_Option $option */
    $option = Mage::getModel('bundle/option');
    $resource = $option->getResource();
    $resource->beginTransaction();
    try {
        $option->setParentId($productId)->setType('select')->setRequired(false)->setPosition(0)
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
    'null default provided' => [true, ['default_title' => null], null],
    'default row missing' => [false, [], null],
]);
