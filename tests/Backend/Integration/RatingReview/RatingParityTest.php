<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

use Maho\Import\Importer\Ratings;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin rating page and import:ratings save a product rating through Mage_Rating_Service_Rating (#1348).
 * The model adds the admin store 0 to the stores of every rating, and the service gives a new rating one
 * option for each star.
 */

const RATING_PARITY_CODES = ['Parity Rating Admin', 'Parity Rating Import', 'Parity Rating Model'];

function ratingParityCleanup(): void
{
    foreach (RATING_PARITY_CODES as $code) {
        $rating = Mage::getModel('rating/rating')->load($code, 'rating_code');
        if ($rating->getId()) {
            $rating->delete();
        }
    }
}

/**
 * @return array{stores: list<int>, options: list<array{code: string, value: int, position: int}>}
 */
function ratingParityState(string $code): array
{
    $rating = Mage::getModel('rating/rating')->load($code, 'rating_code');
    $stores = array_map(intval(...), (array) $rating->getStores());
    sort($stores);
    $options = [];
    foreach ($rating->getOptions() as $option) {
        $options[] = ['code' => (string) $option->getCode(), 'value' => (int) $option->getValue(), 'position' => (int) $option->getPosition()];
    }
    return ['stores' => $stores, 'options' => $options];
}

beforeEach(function () {
    ratingParityCleanup();
    $resource = Mage::getSingleton('core/resource');
    $this->ratingStores = $resource->getConnection('core_read')->fetchAll(
        $resource->getConnection('core_read')->select()->from($resource->getTableName('rating/rating_store')),
    );
});

afterEach(function () {
    ratingParityCleanup();
    // The importer changes the stores of the ratings that the file lists or omits
    $resource = Mage::getSingleton('core/resource');
    $table = $resource->getTableName('rating/rating_store');
    $resource->getConnection('core_write')->delete($table);
    $resource->getConnection('core_write')->insertMultiple($table, $this->ratingStores);
});

it('saves the same stores and star options from the admin and the importer', function () {
    $store = Mage::app()->getStore(1);

    // The importer hides every rating that its file omits, so it runs first
    $path = tempnam(sys_get_temp_dir(), 'ratings') . '.csv';
    file_put_contents($path, "code,stores\nParity Rating Import,{$store->getCode()}\n");
    new Ratings()->import($path);
    unlink($path);
    $admin = TransportParity::admin(Mage_Adminhtml_RatingController::class, 'save', [
        'rating_code' => 'Parity Rating Admin',
        'stores' => [(int) $store->getId()],
        'position' => 0,
    ]);

    $stars = [];
    for ($star = 1; $star <= 5; $star++) {
        $stars[] = ['code' => (string) $star, 'value' => $star, 'position' => $star];
    }
    expect($admin['errors'])->toBe([])
        ->and(ratingParityState('Parity Rating Admin'))->toBe(['stores' => [0, (int) $store->getId()], 'options' => $stars])
        ->and(ratingParityState('Parity Rating Import'))->toBe(ratingParityState('Parity Rating Admin'));
});

it('adds the admin store to a rating that a model save gives no store', function () {
    $entityId = (int) Mage::getModel('rating/rating')->getEntityIdByCode(Mage_Rating_Model_Rating::ENTITY_PRODUCT_CODE);

    Mage::getModel('rating/rating')->setRatingCode('Parity Rating Model')->setEntityId($entityId)->setStores([])->save();

    expect(ratingParityState('Parity Rating Model')['stores'])->toBe([0]);
});
