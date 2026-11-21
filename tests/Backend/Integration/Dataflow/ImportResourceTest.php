<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('loads only pending import rows inside the requested session and range', function (): void {
    $resource = Mage::getResourceSingleton('dataflow/import');
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = $resource->getMainTable();
    $sessionTable = Mage::getSingleton('core/resource')->getTableName('dataflow/session');
    $baseId = (int) $adapter->fetchOne(
        $adapter->select()->from($table, ['max_id' => 'MAX(import_id)']),
    ) + 10;
    $sessionId = random_int(1_000_000, 2_000_000);

    $adapter->beginTransaction();
    try {
        foreach ([$sessionId, $sessionId + 1] as $rowSessionId) {
            $adapter->insert($sessionTable, [
                'session_id' => $rowSessionId,
                'user_id' => 0,
            ]);
        }
        foreach ([
            [$baseId + 1, $sessionId, 0],
            [$baseId + 2, $sessionId, 0],
            [$baseId + 3, $sessionId, 1],
            [$baseId + 4, $sessionId, 0],
            [$baseId + 5, $sessionId, 0],
            [$baseId + 6, $sessionId + 1, 0],
        ] as [$importId, $rowSessionId, $status]) {
            $adapter->insert($table, [
                'import_id' => $importId,
                'session_id' => $rowSessionId,
                'serial_number' => $importId - $baseId,
                'value' => 'row-' . $importId,
                'status' => $status,
            ]);
        }

        $rows = $resource->loadBySessionId($sessionId, $baseId + 2, $baseId + 4);
        $total = $resource->loadTotalBySessionId($sessionId);

        expect(array_map('intval', array_column($rows, 'import_id')))
            ->toBe([$baseId + 2, $baseId + 4])
            ->and((int) $total['cnt'])->toBe(4)
            ->and((int) $total['min'])->toBe($baseId + 1)
            ->and((int) $total['max'])->toBe($baseId + 5);
    } finally {
        $adapter->rollBack();
    }
});
