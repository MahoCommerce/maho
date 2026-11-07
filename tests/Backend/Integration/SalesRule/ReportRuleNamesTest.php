<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('lists each rule name of the coupon report once, without the empty name', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('salesrule/coupon_aggregated');
    $where = ['coupon_code IN (?)' => ['MAHO-RN-A', 'MAHO-RN-B', 'MAHO-RN-C']];
    $adapter->delete($table, $where);

    foreach ([['MAHO-RN-A', 'Maho Rule Names A'], ['MAHO-RN-B', 'Maho Rule Names A'], ['MAHO-RN-C', '']] as [$coupon, $ruleName]) {
        $adapter->insert($table, [
            'period'       => '2026-01-15',
            'store_id'     => 1,
            'order_status' => 'pending',
            'coupon_code'  => $coupon,
            'rule_name'    => $ruleName,
            'coupon_uses'  => 1,
        ]);
    }

    try {
        $names = Mage::getResourceModel('salesrule/report_rule')->getUniqRulesNamesList();

        expect(array_count_values($names)['Maho Rule Names A'] ?? 0)->toBe(1)
            ->and($names)->not->toContain('');
    } finally {
        $adapter->delete($table, $where);
    }
});
