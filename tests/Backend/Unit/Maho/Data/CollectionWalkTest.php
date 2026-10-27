<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Data\Collection::walk()', function () {
    beforeEach(function () {
        $this->collection = new \Maho\Data\Collection();
        $this->collection->addItem(new \Maho\DataObject(['id' => 1]));
        $this->collection->addItem(new \Maho\DataObject(['id' => 2]));
    });

    it('calls a method of each item', function () {
        expect($this->collection->walk('getId'))->toBe([1 => 1, 2 => 2]);
    });

    it('passes each item and the same arguments to a callback', function () {
        $results = $this->collection->walk(fn(\Maho\DataObject $item, string $suffix): string => $item->getId() . $suffix, ['x']);

        expect($results)->toBe([1 => '1x', 2 => '2x']);
    });
});
