<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\DataObject\Cache;

it('expands {hash} and keeps aliases for the same cached object', function () {
    $cache = new Cache();
    $object = new DataObject(['value' => 'one']);
    $other = clone $object;
    $key = $cache->save($object, 'item-{hash}');

    expect($key)->toBe('item-' . spl_object_id($object))
        ->and($cache->save($object, 'alias'))->toBe($key)
        ->and($cache->load('alias'))->toBe($object)
        ->and($cache->findByHash(substr($key, 5)))->toBe($object)
        ->and($cache->save($other, 'item-{hash}'))->not->toBe($key)
        ->and($cache->delete($key))->toBeTrue()
        ->and($cache->findByHash(substr($key, 5)))->toBeNull()
        ->and($cache->save($object, 'restored'))->toBe('restored');
});

it('stops at a recursive object in debug output', function () {
    $object = new DataObject();
    $object->setData('self', $object);
    $visited = [];
    $output = $object->debug(null, $visited);

    expect($output)->toBe(['self (Maho\DataObject)' => '*** RECURSION ***'])
        ->and($object->debug(null, $visited))->toBe('*** RECURSION ***');
});
