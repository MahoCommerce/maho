<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class)->group('backend', 'db', 'timestamp');

// Regression for #856: MySQL-only ON UPDATE CURRENT_TIMESTAMP silently downgraded to plain
// CURRENT_TIMESTAMP on PgSQL/SQLite, so updated_at never auto-bumped on those engines.
// The fix moves the bump into PHP _beforeSave(); these tests prove it works regardless
// of engine, since the test suite runs against whichever DB is configured.

describe('timestamp auto-bump', function () {
    test('payment/restriction stamps created_at and bumps updated_at on save', function () {
        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_write');
        $table = $resource->getTableName('payment/restriction');

        $model = Mage::getModel('payment/restriction');
        $model->setName('Parity Test');
        $model->setStatus(1);
        $model->setPaymentMethods('checkmo');
        $model->save();

        $id = (int) $model->getId();
        expect($id)->toBeGreaterThan(0);
        expect($model->getCreatedAt())->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        expect($model->getUpdatedAt())->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');

        $originalCreatedAt = $model->getCreatedAt();
        $stale = '2020-01-01 00:00:00';

        // Overwrite updated_at directly in the DB so the second save must visibly advance it.
        $conn->update($table, ['updated_at' => $stale], ['restriction_id = ?' => $id]);

        $reloaded = Mage::getModel('payment/restriction')->load($id);
        expect($reloaded->getUpdatedAt())->toBe($stale);

        $reloaded->setName('Parity Test Updated');
        $reloaded->save();

        $final = Mage::getModel('payment/restriction')->load($id);
        expect($final->getUpdatedAt())->not->toBe($stale);
        expect($final->getCreatedAt())->toBe($originalCreatedAt);

        $final->delete();
    });

    test('core/flag stamps last_update on save and bumps it on re-save', function () {
        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_write');
        $table = $resource->getTableName('core/flag');

        $flagCode = 'timestamp_auto_bump_' . uniqid();
        $flag = Mage::getModel('core/flag', ['flag_code' => $flagCode]);
        $flag->setFlagData(['step' => 1]);
        $flag->save();

        $id = (int) $flag->getId();
        expect($id)->toBeGreaterThan(0);
        expect($flag->getLastUpdate())->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');

        $stale = '2020-01-01 00:00:00';
        $conn->update($table, ['last_update' => $stale], ['flag_id = ?' => $id]);

        $reloaded = Mage::getModel('core/flag', ['flag_code' => $flagCode])->loadSelf();
        expect($reloaded->getLastUpdate())->toBe($stale);

        $reloaded->setFlagData(['step' => 2]);
        $reloaded->save();

        $final = Mage::getModel('core/flag', ['flag_code' => $flagCode])->loadSelf();
        expect($final->getLastUpdate())->not->toBe($stale);

        $final->delete();
    });
});
