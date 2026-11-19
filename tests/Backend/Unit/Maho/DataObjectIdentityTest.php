<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\DataObject\Cache;

it('keeps hash placeholders and aliases for the same cached object', function () {
    $cache = new Cache();
    $object = new DataObject(['value' => 'one']);
    $other = clone $object;
    $key = $cache->save($object, 'item-{hash}');

    expect($key)->toMatch('/^item-[0-9a-f]{16}0{16}$/')
        ->and($cache->save($object, 'alias'))->toBe($key)
        ->and($cache->load('alias'))->toBe($object)
        ->and($cache->save($other, 'item-{hash}'))->not->toBe($key)
        ->and($cache->delete($key))->toBeTrue()
        ->and($cache->save($object, 'restored'))->toBe('restored');
});

it('keeps the visited hash of a recursive object in debug output', function () {
    $object = new DataObject();
    $object->setData('self', $object);
    $visited = [];
    $output = $object->debug(null, $visited);

    expect($output)->toBe(['self (Maho\DataObject)' => '*** RECURSION ***'])
        ->and(array_key_first($visited))->toMatch('/^[0-9a-f]{16}0{16}$/')
        ->and($object->debug(null, $visited))->toBe('*** RECURSION ***');
});
