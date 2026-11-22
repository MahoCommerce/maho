<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Data\Collection::each()', function () {
    it('replaces each item with the result of the callback', function () {
        $collection = new \Maho\Data\Collection();
        $collection->addItem(new \Maho\DataObject(['id' => 1]));
        $collection->addItem(new \Maho\DataObject(['id' => 2]));

        $collection->each(fn(\Maho\DataObject $item): \Maho\DataObject => new \Maho\DataObject(['id' => $item->getId() * 10]));

        expect($collection->getColumnValues('id'))->toBe([10, 20]);
    });
});
