<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests Weee helper getApplied/setApplied serialization round-trips.
 * The weee_tax_applied field stores an array of tax entries as a
 * serialized (now JSON) string on quote/order items.
 */
describe('Weee helper getApplied reads both formats', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('weee');
    });

    it('reads legacy serialized weee_tax_applied from order item', function () {
        $original = [
            ['title' => 'ECO TAX', 'base_amount' => '5.00', 'amount' => '5.00'],
            ['title' => 'RECYCLING', 'base_amount' => '2.50', 'amount' => '2.50'],
        ];

        $item = new Maho\DataObject(['weee_tax_applied' => serialize($original)]);

        $result = $this->helper->getApplied($item);
        expect($result)->toHaveCount(2)
            ->and($result[0]['title'])->toBe('ECO TAX')
            ->and($result[1]['title'])->toBe('RECYCLING');
    });

    it('reads JSON weee_tax_applied from order item', function () {
        $original = [
            ['title' => 'ECO TAX', 'base_amount' => '5.00', 'amount' => '5.00'],
        ];

        $item = new Maho\DataObject(['weee_tax_applied' => Mage::helper('core')->jsonEncode($original)]);

        $result = $this->helper->getApplied($item);
        expect($result)->toHaveCount(1)
            ->and($result[0]['title'])->toBe('ECO TAX');
    });

    it('returns empty array when weee_tax_applied is empty', function () {
        $item = new Maho\DataObject(['weee_tax_applied' => '']);
        expect($this->helper->getApplied($item))->toBe([]);
    });

    it('returns empty array when weee_tax_applied is null', function () {
        $item = new Maho\DataObject();
        expect($this->helper->getApplied($item))->toBe([]);
    });
});

describe('Weee helper setApplied writes JSON', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('weee');
    });

    it('stores value as JSON string', function () {
        $data = [['title' => 'ECO TAX', 'amount' => '5.00']];

        $item = new Maho\DataObject();
        $this->helper->setApplied($item, $data);

        $raw = $item->getWeeeTaxApplied();
        expect($raw)->toBeString()
            ->and(json_validate($raw))->toBeTrue()
            ->and($raw)->not->toStartWith('a:');
    });

    it('round-trips through setApplied → getApplied', function () {
        $original = [
            [
                'title' => 'ECO TAX',
                'base_amount' => '5.00',
                'amount' => '5.00',
                'base_row_amount' => '10.00',
                'row_amount' => '10.00',
            ],
        ];

        $item = new Maho\DataObject();
        $this->helper->setApplied($item, $original);
        $result = $this->helper->getApplied($item);

        expect($result)->toBe($original);
    });

    it('migration: legacy → getApplied → setApplied → getApplied', function () {
        $original = [
            ['title' => 'ECO TAX', 'amount' => '5.00'],
            ['title' => 'RECYCLING', 'amount' => '2.50'],
        ];

        // Step 1: Read from legacy
        $item = new Maho\DataObject(['weee_tax_applied' => serialize($original)]);
        $data = $this->helper->getApplied($item);
        expect($data)->toBe($original);

        // Step 2: Write back as JSON
        $this->helper->setApplied($item, $data);
        $raw = $item->getWeeeTaxApplied();
        expect(json_validate($raw))->toBeTrue();

        // Step 3: Read again from JSON
        expect($this->helper->getApplied($item))->toBe($original);
    });
});
