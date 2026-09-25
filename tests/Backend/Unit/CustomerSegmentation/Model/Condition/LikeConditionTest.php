<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Segment Condition LIKE', function () {
    test('lets the adapter build LIKE and NOT LIKE, so they ignore case on every engine', function () {
        // PostgreSQL compares a plain LIKE with case, so the adapter turns it into ILIKE there.
        $adapter = $this->createMock(Maho\Db\Adapter\AdapterInterface::class);
        $adapter->expects($this->exactly(2))
            ->method('prepareSqlCondition')
            ->willReturnCallback(fn(string $field, array $condition) => $field . ' ' . array_key_first($condition) . ' ' . current($condition));

        $condition = Mage::getModel('customersegmentation/segment_condition_order_items');
        $build = new ReflectionMethod($condition, 'buildSqlCondition');

        expect($build->invoke($condition, $adapter, 'oi.name', 'LIKE', 'shirt'))->toBe('oi.name like %shirt%');
        expect($build->invoke($condition, $adapter, 'oi.name', 'NOT LIKE', 'shirt'))->toBe('oi.name nlike %shirt%');
    });
});
