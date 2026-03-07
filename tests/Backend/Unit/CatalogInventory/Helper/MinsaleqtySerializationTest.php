<?php

/**
 * Maho
 *
 * @package    Mage_CatalogInventory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests CatalogInventory Minsaleqty helper serialization round-trips.
 *
 * This helper has its own _serializeValue/_unserializeValue that use
 * UnserializeArray internally. The stored format in core_config_data can be:
 * - A plain numeric string like "1" (single qty for all groups)
 * - A JSON object like {"0":1,"1":5} (per-group min qty)
 * - Legacy: a PHP serialized array
 */
describe('Minsaleqty helper _unserializeValue reads both formats', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('cataloginventory/minsaleqty');
        // Use reflection to test protected methods
        $this->unserialize = new ReflectionMethod($this->helper, '_unserializeValue');
        $this->serialize = new ReflectionMethod($this->helper, '_serializeValue');
    });

    it('reads plain numeric value as all-groups qty', function () {
        $result = $this->unserialize->invoke($this->helper, '5');
        expect($result)->toBe([
            Mage_Customer_Model_Group::CUST_GROUP_ALL => 5.0,
        ]);
    });

    it('reads legacy serialized per-group config', function () {
        $original = [0 => 1.0, 1 => 5.0, 2 => 10.0];
        $legacy = serialize($original);

        $result = $this->unserialize->invoke($this->helper, $legacy);
        expect($result)->toBe($original);
    });

    it('reads JSON per-group config', function () {
        $original = [0 => 1.0, 1 => 5.0, 2 => 10.0];
        $json = Mage::helper('core')->jsonEncode($original);

        $result = $this->unserialize->invoke($this->helper, $json);
        // JSON sequential keys: {"0":1,"1":5,"2":10} or [1,5,10]
        // json_encode with sequential int keys produces [1,5,10]
        // json_decode of [1,5,10] produces array with int keys
        expect($result[0])->toBe(1)
            ->and($result[1])->toBe(5)
            ->and($result[2])->toBe(10);
    });

    it('returns empty array for empty value', function () {
        expect($this->unserialize->invoke($this->helper, ''))->toBe([]);
        expect($this->unserialize->invoke($this->helper, null))->toBe([]);
    });
});

describe('Minsaleqty helper _serializeValue writes JSON', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('cataloginventory/minsaleqty');
        $this->serialize = new ReflectionMethod($this->helper, '_serializeValue');
        $this->unserialize = new ReflectionMethod($this->helper, '_unserializeValue');
    });

    it('serializes single all-groups value as plain number', function () {
        // When only CUST_GROUP_ALL is set, returns plain number string
        $input = [Mage_Customer_Model_Group::CUST_GROUP_ALL => 5.0];
        $result = $this->serialize->invoke($this->helper, $input);
        expect($result)->toBe('5');
    });

    it('serializes multi-group value as JSON', function () {
        $input = [0 => 1.0, 1 => 5.0];
        $result = $this->serialize->invoke($this->helper, $input);
        expect(json_validate($result))->toBeTrue();
    });

    it('serializes numeric input as plain number string', function () {
        $result = $this->serialize->invoke($this->helper, 5);
        expect($result)->toBe('5');
    });

    it('round-trips per-group config through serialize → unserialize', function () {
        $input = [0 => 1.0, 1 => 5.0, 2 => 10.0];

        $serialized = $this->serialize->invoke($this->helper, $input);
        $result = $this->unserialize->invoke($this->helper, $serialized);

        // Values should match (int/float difference is OK for arithmetic)
        expect(count($result))->toBe(3)
            ->and((float) $result[0])->toBe(1.0)
            ->and((float) $result[1])->toBe(5.0)
            ->and((float) $result[2])->toBe(10.0);
    });
});

describe('Minsaleqty helper makeArrayFieldValue/makeStorableArrayFieldValue round-trip', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('cataloginventory/minsaleqty');
    });

    it('round-trips through admin form format and back', function () {
        // Simulate: DB value → admin form → save back
        $dbValue = Mage::helper('core')->jsonEncode([0 => 1.0, 1 => 5.0]);

        // Load into admin form
        $formValue = $this->helper->makeArrayFieldValue($dbValue);
        expect($formValue)->toBeArray();

        // Each entry should have customer_group_id and min_sale_qty
        foreach ($formValue as $row) {
            expect($row)->toHaveKey('customer_group_id')
                ->and($row)->toHaveKey('min_sale_qty');
        }

        // Save back from admin form
        $storable = $this->helper->makeStorableArrayFieldValue($formValue);
        expect($storable)->toBeString();

        // Verify the stored value can be read back
        $readBack = $this->helper->makeArrayFieldValue($storable);
        expect($readBack)->toBeArray();
    });

    it('round-trips legacy serialized value through admin form', function () {
        $dbValue = serialize([0 => 1.0, 1 => 5.0]);

        $formValue = $this->helper->makeArrayFieldValue($dbValue);
        expect($formValue)->toBeArray();

        $storable = $this->helper->makeStorableArrayFieldValue($formValue);

        // After round-trip, should be stored as JSON (not PHP serialized)
        if (json_validate($storable)) {
            expect(json_validate($storable))->toBeTrue();
        } else {
            // It's a plain number if single group — that's fine
            expect(is_numeric($storable))->toBeTrue();
        }
    });
});
