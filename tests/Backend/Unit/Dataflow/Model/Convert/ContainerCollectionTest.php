<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the existing containers when it adds one without a name', function (string $collectionClass, string $containerClass) {
    $collection = new $collectionClass();
    $named = new $containerClass();
    $first = new $containerClass();
    $second = new $containerClass();

    $collection->addItem(1, $named);
    $collection->addItem(null, $first);
    $collection->addItem(null, $second);

    expect($collection->getItem(1))->toBe($named)
        ->and($collection->hasItem(2))->toBeTrue()
        ->and($collection->getItem(2))->toBe($first)
        ->and($collection->getItem(3))->toBe($second);
})->with([
    'Dataflow' => [Mage_Dataflow_Model_Convert_Container_Collection::class, Mage_Dataflow_Model_Convert_Container_Generic::class],
    'Convert' => [Maho\Convert\Container\Collection::class, Maho\Convert\Container\Generic::class],
]);
