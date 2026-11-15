<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// A bundle with a dynamic price accepts no custom option. A save must not delete the options that it has.

function dynamicBundleProductId(): int
{
    $id = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        ->addAttributeToFilter('price_type', Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if ($id === 0) {
        test()->markTestSkipped('The store has no bundle product with a dynamic price');
    }
    return $id;
}

/**
 * @return list<int> the IDs of the custom options of product $productId in the database
 */
function dynamicBundleStoredOptionIds(int $productId): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return array_map('intval', $adapter->fetchCol(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog/product_option'), ['option_id'])
            ->where('product_id = ?', $productId)
            ->order('option_id ASC'),
    ));
}

/**
 * Write a custom option of product $productId into the database, as a store has it from the time when the price
 * of the bundle was fixed.
 */
function dynamicBundleInsertOption(int $productId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $before = dynamicBundleStoredOptionIds($productId);
    $adapter->insert($resource->getTableName('catalog/product_option'), [
        'product_id' => $productId,
        'type' => Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD,
        'is_require' => 0,
        'sort_order' => 0,
    ]);
    $optionId = (int) max(array_diff(dynamicBundleStoredOptionIds($productId), $before));
    $adapter->insert($resource->getTableName('catalog/product_option_title'), [
        'option_id' => $optionId,
        'store_id' => 0,
        'title' => 'Pest stored option',
    ]);
    return $optionId;
}

/**
 * Delete the options of product $productId that are not in $keep.
 *
 * @param list<int> $keep
 */
function dynamicBundleRestore(int $productId, array $keep): void
{
    $resource = Mage::getSingleton('core/resource');
    foreach (array_diff(dynamicBundleStoredOptionIds($productId), $keep) as $optionId) {
        $resource->getConnection('core_write')->delete($resource->getTableName('catalog/product_option'), ['option_id = ?' => $optionId]);
    }
}

it('keeps the stored custom options in a save that submits no option', function () {
    $productId = dynamicBundleProductId();
    $before = dynamicBundleStoredOptionIds($productId);

    try {
        $optionId = dynamicBundleInsertOption($productId);

        Mage::getModel('catalog/product')->setStoreId(0)->load($productId)->setDataChanges(true)->save();

        expect(dynamicBundleStoredOptionIds($productId))->toBe([...$before, $optionId]);
    } finally {
        dynamicBundleRestore($productId, $before);
    }
});

it('ignores a submitted custom option and keeps the stored ones', function () {
    $productId = dynamicBundleProductId();
    $before = dynamicBundleStoredOptionIds($productId);
    $submitted = [
        'title' => 'Pest submitted option',
        'type' => Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD,
        'is_require' => '0',
        'sort_order' => '0',
        'price' => '1',
        'price_type' => 'fixed',
        'is_delete' => '',
    ];

    try {
        $optionId = dynamicBundleInsertOption($productId);

        Mage::getModel('catalog/product')->setStoreId(0)->load($productId)
            ->setProductOptions([
                ['option_id' => (string) $optionId] + $submitted,
                ['option_id' => '0'] + $submitted,
            ])
            ->setCanSaveCustomOptions(true)
            ->save();

        expect(dynamicBundleStoredOptionIds($productId))->toBe([...$before, $optionId]);
    } finally {
        dynamicBundleRestore($productId, $before);
    }
});

it('deletes a custom option that a save asks to delete', function () {
    $productId = dynamicBundleProductId();
    $before = dynamicBundleStoredOptionIds($productId);

    try {
        $keptId = dynamicBundleInsertOption($productId);
        $deletedId = dynamicBundleInsertOption($productId);

        Mage::getModel('catalog/product')->setStoreId(0)->load($productId)
            ->setProductOptions([['option_id' => (string) $deletedId, 'is_require' => '0', 'is_delete' => '1']])
            ->setCanSaveCustomOptions(true)
            ->save();

        expect(dynamicBundleStoredOptionIds($productId))->toBe([...$before, $keptId]);
    } finally {
        dynamicBundleRestore($productId, $before);
    }
});
