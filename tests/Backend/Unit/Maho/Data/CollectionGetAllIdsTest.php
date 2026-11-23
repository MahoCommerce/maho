<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Data\Collection::getAllIds()', function () {
    it('returns the ID of each item', function () {
        $collection = new \Maho\Data\Collection();
        $collection->addItem(new \Maho\DataObject(['id' => 1]));
        $collection->addItem(new \Maho\DataObject(['id' => 'two']));

        expect($collection->getAllIds())->toBe([1, 'two']);
    });

    it('skips an item that has no ID', function () {
        $collection = new \Maho\Data\Collection();
        $collection->addItem(new \Maho\DataObject(['id' => 1]));
        $collection->addItem(new \Maho\DataObject(['name' => 'no id']));

        expect($collection->getAllIds())->toBe([1]);
    });
});
