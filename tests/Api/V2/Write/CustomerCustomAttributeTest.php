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
const CUSTOM_ATTR_STOREFRONT_CODE = 'pest_api_vat_ref';
const CUSTOM_ATTR_FLOOR_CODE = 'pest_api_floor';

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

    Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType('customer')->getId(),
        'attribute_code' => CUSTOM_ATTR_STOREFRONT_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'VAT Reference',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => ['customer_account_create', 'customer_account_edit', 'adminhtml_customer'],
    ])->save();

    Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType('customer_address')->getId(),
        'attribute_code' => CUSTOM_ATTR_FLOOR_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Floor',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_system' => 0,
        'used_in_forms' => ['customer_address_edit', 'adminhtml_customer_address'],
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
        foreach ([
            [CUSTOM_ATTR_CUSTOMER_CODE, 'customer'],
            [CUSTOM_ATTR_STOREFRONT_CODE, 'customer'],
            [CUSTOM_ATTR_ADDRESS_CODE, 'customer_address'],
            [CUSTOM_ATTR_FLOOR_CODE, 'customer_address'],
        ] as [$code, $type]) {
            $attribute = Mage::getModel('customer/attribute')->loadByCode($type, $code);
            if ($attribute->getId()) {
                $attribute->delete();
            }
        }
        foreach (['sales/quote_address', 'sales/order_address'] as $table) {
            $table = $resource->getTableName($table);
            foreach ([CUSTOM_ATTR_ADDRESS_CODE, CUSTOM_ATTR_FLOOR_CODE] as $code) {
                if ($write->tableColumnExists($table, $code)) {
                    $write->dropColumn($table, $code);
                }
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

    it('refuses an attribute of the admin form on anonymous registration', function (): void {
        $response = apiPost('/api/rest/v2/customers', [
            'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
            'password' => 'PestWrite1234!',
            'firstname' => 'Pest',
            'lastname' => 'Anonymous',
            'customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-ANON'],
        ]);

        expect($response['status'])->toBe(422)
            ->and(array_column($response['json']['details']['errors'] ?? [], 'field'))->toContain('customAttributes.' . CUSTOM_ATTR_CUSTOMER_CODE);
    });

    it('hides an attribute of the admin form from the token of the customer', function (): void {
        $create = customAttrTestCreateCustomer(['customAttributes' => [
            CUSTOM_ATTR_CUSTOMER_CODE => 'RN-789',
            CUSTOM_ATTR_STOREFRONT_CODE => 'VR-789',
        ]]);
        $id = (int) $create['json']['id'];

        $read = apiGet('/api/rest/v2/customers/me', customerToken($id));
        expect($read['status'])->toBe(200)
            ->and($read['json']['customAttributes'] ?? [])->not->toHaveKey(CUSTOM_ATTR_CUSTOMER_CODE)
            ->and($read['json']['customAttributes'][CUSTOM_ATTR_STOREFRONT_CODE] ?? null)->toBe('VR-789');
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

describe('Address customAttributes (GraphQL)', function (): void {

    it('keeps a stored value when a customer updates an address and the attribute left the admin address form', function (): void {
        $customerId = (int) customAttrTestCreateCustomer()['json']['id'];
        $southId = $GLOBALS['_custom_attr_south_id'];
        $created = apiPost(
            "/api/rest/v2/customers/{$customerId}/addresses",
            customAttrTestAddress(['customAttributes' => [CUSTOM_ATTR_ADDRESS_CODE => $southId]]),
            adminToken(),
        );
        $addressId = (int) $created['json']['id'];

        $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', CUSTOM_ATTR_ADDRESS_CODE);
        $attribute->setData('used_in_forms', ['customer_address_edit'])->save();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();

        try {
            $response = gqlQuery(
                'mutation { updateAddress(input: {id: "' . $addressId . '", city: "Torino"}) { address { id city } } }',
                [],
                customerToken($customerId),
            );
        } finally {
            $attribute->setData('used_in_forms', ['adminhtml_customer_address'])->save();
            Mage::app()->cleanCache();
            Mage::getSingleton('eav/config')->clear();
        }

        expect($response['json'])->not->toHaveKey('errors')
            ->and($response['json']['data']['updateAddress']['address']['city'] ?? null)->toBe('Torino')
            ->and((string) Mage::getModel('customer/address')->load($addressId)->getData(CUSTOM_ATTR_ADDRESS_CODE))->toBe($southId);
    });

});

describe('Required customAttributes (REST)', function (): void {

    it('refuses a new customer with no value for a required attribute, and still registers an anonymous customer', function (): void {
        $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', CUSTOM_ATTR_CUSTOMER_CODE);
        $attribute->setIsRequired(1)->save();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();

        try {
            $admin = customAttrTestCreateCustomer();
            $anonymous = apiPost('/api/rest/v2/customers', [
                'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
                'password' => 'PestWrite1234!',
                'firstname' => 'Pest',
                'lastname' => 'Anonymous',
            ]);
        } finally {
            $attribute->setIsRequired(0)->save();
            Mage::app()->cleanCache();
            Mage::getSingleton('eav/config')->clear();
        }

        expect($admin['status'])->toBe(422)
            ->and(array_column($admin['json']['details']['errors'] ?? [], 'field'))->toContain('customAttributes.' . CUSTOM_ATTR_CUSTOMER_CODE)
            ->and($anonymous['status'])->toBeSuccessful();
    });

});

describe('Storefront customAttributes (REST)', function (): void {

    it('registers a guest with an attribute of the registration form', function (): void {
        $response = apiPost('/api/rest/v2/customers', [
            'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
            'password' => 'PestWrite1234!',
            'firstname' => 'Pest',
            'lastname' => 'Guest',
            'customAttributes' => [CUSTOM_ATTR_STOREFRONT_CODE => 'VR-1'],
        ]);
        expect($response['status'])->toBeSuccessful()
            ->and($response['json']['customAttributes'][CUSTOM_ATTR_STOREFRONT_CODE] ?? null)->toBe('VR-1');

        $read = apiGet('/api/rest/v2/customers/' . (int) $response['json']['id'], adminToken());
        expect($read['json']['customAttributes'][CUSTOM_ATTR_STOREFRONT_CODE] ?? null)->toBe('VR-1');
    });

    it('requires a required attribute of the registration form on registration', function (): void {
        $attribute = Mage::getModel('customer/attribute')->loadByCode('customer', CUSTOM_ATTR_STOREFRONT_CODE);
        $attribute->setIsRequired(1)->save();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();

        try {
            $response = apiPost('/api/rest/v2/customers', [
                'email' => CUSTOM_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
                'password' => 'PestWrite1234!',
                'firstname' => 'Pest',
                'lastname' => 'Guest',
            ]);
        } finally {
            $attribute->setIsRequired(0)->save();
            Mage::app()->cleanCache();
            Mage::getSingleton('eav/config')->clear();
        }

        expect($response['status'])->toBe(422)
            ->and(array_column($response['json']['details']['errors'] ?? [], 'field'))->toContain('customAttributes.' . CUSTOM_ATTR_STOREFRONT_CODE);
    });

    it('lets a customer update an attribute of the account form, and not one of the admin form', function (): void {
        $id = (int) customAttrTestCreateCustomer()['json']['id'];

        $update = apiPut('/api/rest/v2/customers/me', ['customAttributes' => [CUSTOM_ATTR_STOREFRONT_CODE => 'VR-2']], customerToken($id));
        $refused = apiPut('/api/rest/v2/customers/me', ['customAttributes' => [CUSTOM_ATTR_CUSTOMER_CODE => 'RN-2']], customerToken($id));

        expect($update['status'])->toBeSuccessful()
            ->and($update['json']['customAttributes'][CUSTOM_ATTR_STOREFRONT_CODE] ?? null)->toBe('VR-2')
            ->and($refused['status'])->toBe(422)
            ->and(apiGet("/api/rest/v2/customers/{$id}", adminToken())['json']['customAttributes'][CUSTOM_ATTR_CUSTOMER_CODE] ?? null)->toBeNull();
    });

    it('lets a customer write an attribute of the address book on a new address', function (): void {
        $id = (int) customAttrTestCreateCustomer()['json']['id'];

        $created = apiPost(
            '/api/rest/v2/customers/me/addresses',
            customAttrTestAddress(['customAttributes' => [CUSTOM_ATTR_FLOOR_CODE => '3rd']]),
            customerToken($id),
        );
        $refused = apiPost(
            '/api/rest/v2/customers/me/addresses',
            customAttrTestAddress(['customAttributes' => [CUSTOM_ATTR_ADDRESS_CODE => $GLOBALS['_custom_attr_south_id']]]),
            customerToken($id),
        );

        expect($created['status'])->toBeSuccessful()
            ->and($created['json']['customAttributes'][CUSTOM_ATTR_FLOOR_CODE] ?? null)->toBe('3rd')
            ->and($created['json']['customAttributes'] ?? [])->not->toHaveKey(CUSTOM_ATTR_ADDRESS_CODE)
            ->and($refused['status'])->toBe(422);
    });

    it('hides an admin only address attribute from the customer who owns the address', function (): void {
        $customerId = (int) customAttrTestCreateCustomer()['json']['id'];
        $created = apiPost(
            "/api/rest/v2/customers/{$customerId}/addresses",
            customAttrTestAddress(['customAttributes' => [
                CUSTOM_ATTR_ADDRESS_CODE => $GLOBALS['_custom_attr_south_id'],
                CUSTOM_ATTR_FLOOR_CODE => '3rd',
            ]]),
            adminToken(),
        );
        expect($created['status'])->toBeSuccessful();

        $read = apiGet('/api/rest/v2/customers/me/addresses/' . (int) $created['json']['id'], customerToken($customerId));

        expect($read['status'])->toBe(200)
            ->and($read['json']['customAttributes'] ?? [])->not->toHaveKey(CUSTOM_ATTR_ADDRESS_CODE)
            ->and($read['json']['customAttributes'][CUSTOM_ATTR_FLOOR_CODE] ?? null)->toBe('3rd');
    });

    it('sets an address attribute on a guest cart and copies it with sameAsShipping', function (): void {
        $create = apiPost('/api/rest/v2/guest-carts', []);
        expect($create['status'])->toBeIn([200, 201]);
        $maskedId = $create['json']['maskedId'];
        trackCreated('quote', (int) $create['json']['id']);
        $address = [
            'firstName' => 'Pest',
            'lastName' => 'Guest',
            'street' => ['123 Setter St'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        $shipping = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address + ['customAttributes' => [CUSTOM_ATTR_FLOOR_CODE => '2nd']]);
        $billing = apiPut("/api/rest/v2/guest-carts/{$maskedId}/billing-address", ['sameAsShipping' => true]);
        $refused = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address + ['customAttributes' => [CUSTOM_ATTR_ADDRESS_CODE => $GLOBALS['_custom_attr_south_id']]]);

        expect($shipping['status'])->toBe(200)
            ->and($shipping['json']['shippingAddress']['customAttributes'][CUSTOM_ATTR_FLOOR_CODE] ?? null)->toBe('2nd')
            ->and($billing['json']['billingAddress']['customAttributes'][CUSTOM_ATTR_FLOOR_CODE] ?? null)->toBe('2nd')
            ->and($refused['status'])->toBe(422);
    });

    it('clears an address attribute when a new cart address write leaves it out', function (): void {
        $create = apiPost('/api/rest/v2/guest-carts', []);
        expect($create['status'])->toBeIn([200, 201]);
        $maskedId = $create['json']['maskedId'];
        trackCreated('quote', (int) $create['json']['id']);
        $address = [
            'firstName' => 'Pest',
            'lastName' => 'Guest',
            'street' => ['123 Setter St'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address + ['customAttributes' => [CUSTOM_ATTR_FLOOR_CODE => '2nd']]);
        $cleared = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address);

        expect($cleared['status'])->toBe(200)
            ->and($cleared['json']['shippingAddress']['customAttributes'][CUSTOM_ATTR_FLOOR_CODE] ?? null)->toBeNull();
    });

    it('requires a required attribute of the checkout address form on a cart address', function (): void {
        $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', CUSTOM_ATTR_FLOOR_CODE);
        $attribute->setIsRequired(1)->save();
        Mage::app()->cleanCache();
        Mage::getSingleton('eav/config')->clear();
        $address = [
            'firstName' => 'Pest',
            'lastName' => 'Guest',
            'street' => ['123 Setter St'],
            'city' => 'Los Angeles',
            'region' => 'California',
            'postcode' => '90210',
            'countryId' => 'US',
            'telephone' => '5550100',
        ];

        try {
            $create = apiPost('/api/rest/v2/guest-carts', []);
            $maskedId = $create['json']['maskedId'];
            trackCreated('quote', (int) $create['json']['id']);
            $missing = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address);
            $given = apiPut("/api/rest/v2/guest-carts/{$maskedId}/shipping-address", $address + ['customAttributes' => [CUSTOM_ATTR_FLOOR_CODE => '1st']]);
        } finally {
            $attribute->setIsRequired(0)->save();
            Mage::app()->cleanCache();
            Mage::getSingleton('eav/config')->clear();
        }

        expect($missing['status'])->toBe(422)
            ->and(array_column($missing['json']['details']['errors'] ?? [], 'field'))->toContain('customAttributes.' . CUSTOM_ATTR_FLOOR_CODE)
            ->and($given['status'])->toBe(200);
    });

});

describe('Storefront customer forms', function (): void {

    it('lists the fields of the registration form to a guest, without the attributes of the admin form', function (): void {
        $response = apiGet('/api/rest/v2/customer-forms/customer_account_create');

        expect($response['status'])->toBe(200);
        $fields = array_merge(...array_column($response['json']['groups'] ?? [], 'fields'));
        $codes = array_column($fields, 'code');
        $field = $fields[array_search(CUSTOM_ATTR_STOREFRONT_CODE, $codes, true)] ?? [];
        expect($codes)->toContain(CUSTOM_ATTR_STOREFRONT_CODE)
            ->and($codes)->not->toContain(CUSTOM_ATTR_CUSTOMER_CODE)
            ->and($field['label'] ?? null)->toBe('VAT Reference')
            ->and($field['input'] ?? null)->toBe('text')
            ->and($field['isRequired'] ?? null)->toBeFalse();
    });

    it('answers 404 for a form that is not a storefront form', function (): void {
        expect(apiGet('/api/rest/v2/customer-forms/adminhtml_customer')['status'])->toBe(404);
    });

    it('serves the GraphQL customerForm query', function (): void {
        $response = gqlQuery('{ customerForm(id: "/api/rest/v2/customer-forms/customer_address_edit") { entityType groups } }');

        expect($response['json']['errors'] ?? [])->toBeEmpty()
            ->and($response['json']['data']['customerForm']['entityType'] ?? null)->toBe('customer_address');
        $codes = array_column(array_merge(...array_column($response['json']['data']['customerForm']['groups'] ?? [], 'fields')), 'code');
        expect($codes)->toContain(CUSTOM_ATTR_FLOOR_CODE)
            ->and($codes)->not->toContain(CUSTOM_ATTR_ADDRESS_CODE);
    });

});
