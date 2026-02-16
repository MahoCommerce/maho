<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Integration test for _unserializeField soft-conversion.
 *
 * When a resource model loads a record with legacy PHP-serialized data,
 * _unserializeField in Resource_Abstract should silently rewrite it as JSON.
 *
 * IMPORTANT finding: All 4 Sales resource models (Order_Payment, Quote_Payment,
 * Order_Payment_Transaction, Recurring_Profile) OVERRIDE _unserializeField and
 * do NOT include the soft-conversion write-back. Those models rely entirely on
 * the migration script — no self-healing on load.
 */
describe('Flag model save/load round-trip via DB', function () {
    it('saves as JSON and loads back correctly', function () {
        $original = ['test_key' => 'test_value', 'count' => 42];
        $flagCode = 'serialization_test_' . uniqid();

        // Save a flag with JSON data (normal path)
        // flag_code must be passed via constructor to set $_flagCode
        $flag = Mage::getModel('core/flag', ['flag_code' => $flagCode]);
        $flag->setFlagData($original);
        $flag->save();

        $flagId = $flag->getId();
        expect($flagId)->toBeGreaterThan(0);

        // Verify DB contains JSON (not PHP serialized)
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $raw = $read->fetchOne(
            $read->select()
                ->from($resource->getTableName('core/flag'), ['flag_data'])
                ->where('flag_id = ?', $flagId),
        );
        expect(json_validate($raw))->toBeTrue()
            ->and($raw)->not->toStartWith('a:');

        // Load back and verify data
        $loaded = Mage::getModel('core/flag', ['flag_code' => $flagCode])->loadSelf();
        expect($loaded->getFlagData())->toBe($original);

        // Cleanup
        $loaded->delete();
    });

    it('reads legacy serialized flag_data from DB and re-saves as JSON', function () {
        $original = ['is_built' => true, 'store_id' => 1];
        $flagCode = 'serialization_legacy_test_' . uniqid();

        // Step 1: Save normally to create the row
        $flag = Mage::getModel('core/flag', ['flag_code' => $flagCode]);
        $flag->setFlagData($original);
        $flag->save();
        $flagId = $flag->getId();

        // Step 2: Manually overwrite with legacy serialized data
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $write->update(
            $resource->getTableName('core/flag'),
            ['flag_data' => serialize($original)],
            ['flag_id = ?' => $flagId],
        );

        // Verify DB has serialized data
        $rawBefore = $write->fetchOne(
            $write->select()
                ->from($resource->getTableName('core/flag'), ['flag_data'])
                ->where('flag_id = ?', $flagId),
        );
        expect($rawBefore)->toStartWith('a:');

        // Step 3: Load — getFlagData decodes via String::unserialize
        $loaded = Mage::getModel('core/flag', ['flag_code' => $flagCode])->loadSelf();
        expect($loaded->getFlagData())->toBe($original);

        // Step 4: Save back — setFlagData encodes as JSON
        $loaded->setFlagData($loaded->getFlagData());
        $loaded->save();

        // Step 5: Verify DB now contains JSON
        $rawAfter = $write->fetchOne(
            $write->select()
                ->from($resource->getTableName('core/flag'), ['flag_data'])
                ->where('flag_id = ?', $flagId),
        );
        expect(json_validate($rawAfter))->toBeTrue()
            ->and($rawAfter)->not->toStartWith('a:');

        // Cleanup
        $loaded->delete();
    });
});

describe('Sales Order Payment does NOT soft-convert on load', function () {
    it('loads legacy serialized data but does NOT rewrite the DB row', function () {
        // This documents that Sales resource models override _unserializeField
        // without the soft-conversion write-back. They rely on migration scripts.
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');

        // Create an order + payment
        $order = Mage::getModel('sales/order');
        $order->setData([
            'store_id' => 1,
            'state' => 'new',
            'status' => 'pending',
            'base_grand_total' => 100,
            'grand_total' => 100,
            'base_currency_code' => 'USD',
            'order_currency_code' => 'USD',
            'customer_is_guest' => 1,
            'customer_email' => 'test@example.com',
        ]);
        $order->save();

        $payment = Mage::getModel('sales/order_payment');
        $payment->setData([
            'parent_id' => $order->getId(),
            'method' => 'checkmo',
            'additional_information' => ['method_title' => 'Check / Money order'],
        ]);
        $payment->save();
        $paymentId = $payment->getId();

        // Overwrite with legacy serialized data
        $original = ['method_title' => 'Check / Money order', 'test_field' => 'value'];
        $write->update(
            $resource->getTableName('sales/order_payment'),
            ['additional_information' => serialize($original)],
            ['entity_id = ?' => $paymentId],
        );

        // Load — data is decoded correctly
        $loaded = Mage::getModel('sales/order_payment')->load($paymentId);
        expect($loaded->getData('additional_information'))->toBe($original);

        // But DB still contains PHP serialized data (no soft-conversion)
        $rawAfter = $write->fetchOne(
            $write->select()
                ->from($resource->getTableName('sales/order_payment'), ['additional_information'])
                ->where('entity_id = ?', $paymentId),
        );
        expect($rawAfter)->toStartWith('a:')
            ->and(json_validate($rawAfter))->toBeFalse();

        // Cleanup
        $payment->delete();
        $order->delete();
    });
});
