<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 customAttributes of customers and customer addresses
 *
 * @group write
 */

const CUSTOM_ATTR_EMAIL_PREFIX = 'pest-custom-attr-';
const CUSTOM_ATTR_CUSTOMER_CODE = 'pest_api_reg_no';
const CUSTOM_ATTR_ADDRESS_CODE = 'pest_api_dock';

function customAttrTestCreateCustomer(array $extra = []): array
{
    return apiPost('/api/rest/v2/customers', array_merge([
        'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
        'password' => 'PestWrite1234!',
        'firstname' => 'Pest',
        'lastname' => 'Custom',
    ], $extra), adminToken());
}

function customAttrTestAddress(array $extra = []): array
{
    return array_merge([
        'firstname' => 'Pest',
        'lastname' => 'Custom',
        'street' => ['Via Roma 1'],
        'city' => 'Milano',
        'postcode' => '20121',
        'countryId' => 'IT',
        'telephone' => '+39 02 1234567',
    ], $extra);
}

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
    $eavConfig = Mage::getSingleton('eav/config');

    Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType('customer')->getId(),
        'attribute_code' => CUSTOM_ATTR_CUSTOMER_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Registration Number',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'validate_rules' => ['max_text_length' => 10],
        'used_in_forms' => ['adminhtml_customer'],
    ])->save();

    Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType('customer_address')->getId(),
        'attribute_code' => CUSTOM_ATTR_ADDRESS_CODE,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Dock',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => ['adminhtml_customer_address'],
        'option' => ['value' => ['o1' => ['North Dock'], 'o2' => ['South Dock']], 'order' => ['o1' => 1, 'o2' => 2]],
    ])->save();
    Mage::app()->cleanCache();
    $eavConfig->clear();

    $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', CUSTOM_ATTR_ADDRESS_CODE);
    foreach ($attribute->getSource()->getAllOptions(false) as $option) {
        if ($option['label'] === 'South Dock') {
            $GLOBALS['_custom_attr_south_id'] = (string) $option['value'];
        }
    }
});

afterAll(function (): void {
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $write->delete($resource->getTableName('customer/entity'), ['email LIKE ?' => CUSTOM_ATTR_EMAIL_PREFIX . '%']);
        foreach (['customer' => CUSTOM_ATTR_CUSTOMER_CODE, 'customer_address' => CUSTOM_ATTR_ADDRESS_CODE] as $type => $code) {
            $attribute = Mage::getModel('customer/attribute')->loadByCode($type, $code);
            if ($attribute->getId()) {
                $attribute->delete();
            }
        }
        foreach (['sales/quote_address', 'sales/order_address'] as $table) {
            $table = $resource->getTableName($table);
            if ($write->tableColumnExists($table, CUSTOM_ATTR_ADDRESS_CODE)) {
                $write->dropColumn($table, CUSTOM_ATTR_ADDRESS_CODE);
            }
            $write->resetDdlCache($table);
        }
        Mage::app()->cleanCache();
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('Customer customAttributes (REST)', function (): void {

    it('lets an admin write a customer attribute and read it back', function (): void {
        $create = customAttrTestCreateCustomer(['customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-123']]);
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $read = apiGet("/api/rest/v2/customers/{$id}", adminToken());
        expect($read['json']['customAttributes'][CUSTOM_ATTR_CUSTOMER_CODE] ?? null)->toBe('RN-123');

        $update = apiPut("/api/rest/v2/customers/{$id}", ['customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-456']], adminToken());
        expect($update['status'])->toBeSuccessful()
            ->and($update['json']['customAttributes'][CUSTOM_ATTR_CUSTOMER_CODE] ?? null)->toBe('RN-456');
    });

    it('refuses a code that is not a custom attribute, and a system attribute', function (): void {
        $response = customAttrTestCreateCustomer(['customAttributes' => ['pest_not_an_attribute' => 'x', 'email' => 'a@example.test']]);

        expect($response['status'])->toBe(422);
        $fields = array_column($response['json']['details']['errors'] ?? [], 'field');
        expect($fields)->toContain('customAttributes.pest_not_an_attribute')
            ->and($fields)->toContain('customAttributes.email');
    });

    it('applies the input rules of the attribute', function (): void {
        $response = customAttrTestCreateCustomer(['customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'longer than ten']]);

        expect($response['status'])->toBe(422);
    });

    it('ignores customAttributes on anonymous registration', function (): void {
        $response = apiPost('/api/rest/v2/customers', [
            'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
            'password' => 'PestWrite1234!',
            'firstname' => 'Pest',
            'lastname' => 'Anonymous',
            'customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-ANON'],
        ]);
        expect($response['status'])->toBeSuccessful();

        $read = apiGet('/api/rest/v2/customers/' . (int) $response['json']['id'], adminToken());
        expect($read['status'])->toBe(200)
            ->and($read['json']['customAttributes'] ?? [])->not->toHaveKey(CUSTOM_ATTR_CUSTOMER_CODE);
    });

    it('hides customAttributes from the token of the customer', function (): void {
        $create = customAttrTestCreateCustomer(['customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-789']]);
        $id = (int) $create['json']['id'];

        $read = apiGet('/api/rest/v2/customers/me', customerToken($id));
        expect($read['status'])->toBe(200)
            ->and($read['json']['customAttributes'] ?? null)->toBeNull();
    });

});

describe('Address customAttributes (REST)', function (): void {

    it('lets an admin write an address dropdown with an option id and read it back', function (): void {
        $customerId = (int) customAttrTestCreateCustomer()['json']['id'];
        $southId = $GLOBALS['_custom_attr_south_id'];

        $created = apiPost(
            "/api/rest/v2/customers/{$customerId}/addresses",
            customAttrTestAddress(['customAttributes' => [CUSTOM_ATTR_ADDRESS_CODE => $southId]]),
            adminToken(),
        );
        expect($created['status'])->toBeSuccessful();
        $addressId = (int) $created['json']['id'];

        $read = apiGet("/api/rest/v2/addresses/{$addressId}", adminToken());
        expect((string) ($read['json']['customAttributes'][CUSTOM_ATTR_ADDRESS_CODE] ?? ''))->toBe($southId);
    });

    it('refuses a value that is not an option of the dropdown', function (): void {
        $customerId = (int) customAttrTestCreateCustomer()['json']['id'];

        $response = apiPost(
            "/api/rest/v2/customers/{$customerId}/addresses",
            customAttrTestAddress(['customAttributes' => [CUSTOM_ATTR_ADDRESS_CODE => '999999']]),
            adminToken(),
        );
        expect($response['status'])->toBe(422);
    });

});
