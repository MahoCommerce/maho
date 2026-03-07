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
 * Tests that Mage_Core_Helper_String::unserialize() correctly converts
 * legacy PHP-serialized data (as found in production databases) into
 * the expected PHP values, and that the result can then be re-encoded
 * as JSON without data loss.
 */
describe('Legacy serialized data conversion via core/string helper', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('core/string');
    });

    it('converts serialized product info_buyRequest', function () {
        $original = [
            'uenc' => 'aHR0cDovL2xvY2FsaG9zdC9jYXRhbG9nL3Byb2R1Y3Qvdmlldw==',
            'product' => '42',
            'qty' => 1,
            'related_product' => '',
        ];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        // Re-encode as JSON and verify it roundtrips
        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized configurable super_attribute with integer keys', function () {
        // super_attribute maps attribute_id (int) => option_value_id (string)
        $original = [143 => '25', 92 => '17'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        // Integer keys must survive the JSON roundtrip
        $json = Mage::helper('core')->jsonEncode($result);
        $fromJson = $this->helper->unserialize($json);
        expect($fromJson)->toHaveKey(143)
            ->and($fromJson[143])->toBe('25')
            ->and($fromJson)->toHaveKey(92);
    });

    it('converts serialized bundle_selection_attributes', function () {
        $original = ['qty' => 1.0, 'price' => 10.0, 'default_qty' => 1.0, 'selection_id' => '123'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result['qty'])->toBe(1.0)
            ->and($result['price'])->toBe(10.0)
            ->and($result['selection_id'])->toBe('123');

        // Note: JSON doesn't distinguish 1.0 from 1 for whole numbers.
        // json_encode(1.0) produces "1", and json_decode("1") returns int 1.
        // This is a known, safe type change — the codebase uses these values
        // in arithmetic contexts where int/float is interchangeable.
        $json = Mage::helper('core')->jsonEncode($result);
        $fromJson = $this->helper->unserialize($json);
        expect($fromJson['qty'])->toBe(1)
            ->and($fromJson['price'])->toBe(10)
            ->and($fromJson['selection_id'])->toBe('123');
    });

    it('converts serialized bundle_option_ids with integer values', function () {
        $original = [15, 23, 42];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($json)->toBe('[15,23,42]');
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized EAV validate_rules', function () {
        $original = ['max_text_length' => 255, 'min_text_length' => 1];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized admin_user extra data', function () {
        $original = [
            'configState' => [
                'general_country_grp_1' => '1',
                'general_country_grp_2' => '0',
                'general_country_grp_3' => '1',
            ],
        ];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized weee_tax_applied', function () {
        $original = [[
            'title' => 'ECO TAX',
            'base_amount' => '5.00',
            'amount' => '5.00',
            'base_row_amount' => '10.00',
            'row_amount' => '10.00',
        ]];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result[0]['title'])->toBe('ECO TAX')
            ->and($result[0]['base_amount'])->toBe('5.00');

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized payment additional_information', function () {
        $original = ['method_title' => 'Check / Money order', 'mailing_address' => '123 Main St, Anytown USA'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized widget_parameters', function () {
        $original = ['template' => 'widget/recently_viewed_list.phtml', 'page_size' => '5', 'product_count' => '10'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized index_event new_data', function () {
        $original = [
            'Mage_Catalog_Model_Indexer_Url' => [
                'reindex_all' => true,
                'product_ids' => [42, 99],
            ],
        ];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized applied_taxes from quote address', function () {
        $original = [
            'US-TAX' => [
                'amount' => 8.5,
                'base_amount' => 8.5,
                'percent' => 8.5,
                'id' => 'US-TAX',
                'rates' => [['code' => 'US-TAX', 'title' => 'Sales Tax', 'percent' => 8.5]],
            ],
        ];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result['US-TAX']['percent'])->toBe(8.5)
            ->and($result['US-TAX']['rates'][0]['title'])->toBe('Sales Tax');

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized min_sale_qty config with integer keys', function () {
        // CatalogInventory min_sale_qty: customer_group_id => min_qty
        $original = [0 => '1', 1 => '5', 2 => '10'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        // Sequential integer keys become a JSON array
        $json = Mage::helper('core')->jsonEncode($result);
        expect($json)->toBe('["1","5","10"]');
        expect($this->helper->unserialize($json))->toBe(['1', '5', '10']);
    });

    it('converts serialized min_sale_qty with non-sequential integer keys', function () {
        // customer_group_id => min_qty, but groups 0 and 3 only (non-sequential)
        $original = [0 => '1', 3 => '10'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        // Non-sequential keys become JSON object with string keys
        $json = Mage::helper('core')->jsonEncode($result);
        $fromJson = $this->helper->unserialize($json);
        expect($fromJson)->toHaveKey('0')
            ->and($fromJson['0'])->toBe('1')
            ->and($fromJson)->toHaveKey('3')
            ->and($fromJson['3'])->toBe('10');
    });

    it('converts serialized shipment packages with non-sequential integer keys', function () {
        $original = [1 => ['weight' => 2.5, 'length' => 10.0, 'width' => 8.0, 'height' => 6.0]];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result[1]['weight'])->toBe(2.5);

        // Non-sequential key (starts at 1) becomes JSON object key
        $json = Mage::helper('core')->jsonEncode($result);
        $fromJson = $this->helper->unserialize($json);
        expect($fromJson[1]['weight'])->toBe(2.5);
    });

    it('converts serialized email queue message_parameters', function () {
        $original = ['return_path' => 'noreply@example.com', 'is_html' => true, 'subject' => 'Order Received'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized currency symbols with unicode', function () {
        $original = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts serialized recurring profile order_info', function () {
        $original = ['order_id' => '100', 'increment_id' => '100000001', 'state' => 'processing'];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });

    it('converts deeply nested serialized structures', function () {
        $original = [
            'level1' => [
                'level2' => [
                    'level3' => ['a', 'b', 'c'],
                    'mixed' => [0 => 'zero', 'key' => 'value'],
                ],
            ],
        ];
        $legacy = serialize($original);

        $result = $this->helper->unserialize($legacy);
        expect($result)->toBe($original);

        $json = Mage::helper('core')->jsonEncode($result);
        expect($this->helper->unserialize($json))->toBe($original);
    });
});

describe('isSerializedArrayOrObject detects both JSON and legacy formats', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('core/string');
    });

    it('detects JSON array', function () {
        expect($this->helper->isSerializedArrayOrObject('[1,2,3]'))->toBeTrue();
    });

    it('detects JSON object', function () {
        expect($this->helper->isSerializedArrayOrObject('{"key":"val"}'))->toBeTrue();
    });

    it('rejects JSON scalar string', function () {
        expect($this->helper->isSerializedArrayOrObject('"hello"'))->toBeFalse();
    });

    it('rejects JSON scalar number', function () {
        expect($this->helper->isSerializedArrayOrObject('42'))->toBeFalse();
    });

    it('detects legacy serialized array', function () {
        expect($this->helper->isSerializedArrayOrObject(serialize(['key' => 'val'])))->toBeTrue();
    });

    it('rejects plain string', function () {
        expect($this->helper->isSerializedArrayOrObject('just a string'))->toBeFalse();
    });

    it('rejects null and empty', function () {
        expect($this->helper->isSerializedArrayOrObject(null))->toBeFalse();
        expect($this->helper->isSerializedArrayOrObject(''))->toBeFalse();
    });
});
