<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// The product save gives its options to the option singleton. A list that stays there goes into the next product
// save of the same process, as in an API worker, a CLI import or a test run.

/**
 * The ID of the first enabled product of $typeId in the sample data.
 */
function optionDeleteSampleProductId(string $typeId): int
{
    $id = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', $typeId)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if ($id === 0) {
        test()->markTestSkipped("The store has no {$typeId} product");
    }
    return $id;
}

/**
 * @return list<int> the IDs of the custom options of product $productId in the database
 */
function optionDeleteStoredIds(int $productId): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return array_map('intval', $adapter->fetchCol(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog/product_option'), ['option_id'])
            ->where('product_id = ?', $productId),
    ));
}

function optionDeleteNewOption(): Mage_Catalog_Model_Product_Option
{
    return Mage::getModel('catalog/product_option')
        ->setTitle('Pest delete ' . uniqid())
        ->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD)
        ->setIsRequire(false)
        ->setSortOrder(0);
}

/**
 * Delete the options of product $productId that are not in $keep.
 *
 * @param list<int> $keep
 */
function optionDeleteRestore(int $productId, array $keep): void
{
    foreach (array_diff(optionDeleteStoredIds($productId), $keep) as $optionId) {
        Mage::getModel('catalog/product_option')->load($optionId)->delete();
    }
}

dataset('option delete product types', [
    'simple' => [Mage_Catalog_Model_Product_Type::TYPE_SIMPLE],
    'configurable' => [Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE],
]);

it('deletes a custom option through the service', function (string $typeId) {
    $productId = optionDeleteSampleProductId($typeId);
    $before = optionDeleteStoredIds($productId);
    $service = Mage::getService('catalog/product_option');
    $product = Mage::getModel('catalog/product')->load($productId);

    try {
        $optionId = (int) $service->save($product, optionDeleteNewOption())->getId();
        expect(optionDeleteStoredIds($productId))->toContain($optionId);

        $service->delete($product, $service->getById($product, $optionId));

        expect(optionDeleteStoredIds($productId))->toBe($before);
    } finally {
        optionDeleteRestore($productId, $before);
    }
})->with('option delete product types');

it('does not copy an option into the next product save', function () {
    $productId = optionDeleteSampleProductId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $otherId = optionDeleteSampleProductId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
    $before = optionDeleteStoredIds($productId);
    $otherBefore = optionDeleteStoredIds($otherId);

    try {
        Mage::getService('catalog/product_option')->save(Mage::getModel('catalog/product')->load($productId), optionDeleteNewOption());
        Mage::getModel('catalog/product')->setStoreId(0)->load($otherId)->setDataChanges(true)->save();

        expect(optionDeleteStoredIds($otherId))->toBe($otherBefore)
            ->and(count(optionDeleteStoredIds($productId)))->toBe(count($before) + 1);
    } finally {
        optionDeleteRestore($productId, $before);
        optionDeleteRestore($otherId, $otherBefore);
    }
});

it('does not save a refused option with the next option', function () {
    $productId = optionDeleteSampleProductId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $before = optionDeleteStoredIds($productId);
    $service = Mage::getService('catalog/product_option');
    $product = Mage::getModel('catalog/product')->load($productId);
    $refused = optionDeleteNewOption()
        ->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_DROP_DOWN)
        ->setData('values', []);

    try {
        expect(fn() => $service->save($product, $refused))->toThrow(Mage_Core_Exception_Input::class);
        $service->save($product, optionDeleteNewOption());

        expect(count(optionDeleteStoredIds($productId)))->toBe(count($before) + 1);
    } finally {
        optionDeleteRestore($productId, $before);
    }
});
