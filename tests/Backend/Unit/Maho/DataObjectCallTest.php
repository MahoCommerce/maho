<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\DataObject;
use Maho\Exception;

it('reports the argument types for an invalid method call', function (): void {
    $object = new DataObject();
    $details = new DataObject(['name' => 'Example']);

    expect(fn() => $object->createRecord($details, ['item' => 'Example'], 'Example', 4, true, null))
        ->toThrow(Exception::class, 'Invalid method Maho\DataObject::createRecord(Maho\DataObject, array, string, int, bool, null)');
});
