<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2: an address attribute that shows only for Italy (#1729) is not required for another country, and the API
 * does not keep a value for it.
 *
 * @group write
 */

const DEP_ATTR_EMAIL_PREFIX = 'pest-dep-attr-';
const DEP_ATTR_CODE = 'pest_api_dep_sdi';

function depAttrCustomer(): int
{
    $response = apiPost('/api/rest/v2/customers', [
        'email' => DEP_ATTR_EMAIL_PREFIX . uniqid() . '@example.test',
        'password' => 'PestWrite1234!',
        'firstname' => 'Pest',
        'lastname' => 'Dependency',
    ], adminToken());
    return (int) $response['json']['id'];
}

function depAttrAddress(string $countryId, array $customAttributes = []): array
{
    $address = [
        'firstname' => 'Pest',
        'lastname' => 'Dependency',
        'street' => ['Main Street 1'],
        'city' => 'Springfield',
        'postcode' => '20121',
        'countryId' => $countryId,
        'telephone' => '+39 02 1234567',
    ];
    if ($countryId === 'US') {
        $address['regionId'] = 12;
        $address['postcode'] = '90001';
    }
    return $customAttributes ? $address + ['customAttributes' => $customAttributes] : $address;
}

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
    $eavConfig = Mage::getSingleton('eav/config');
    Mage::getModel('customer/attribute')->setData([
        'entity_type_id' => (int) $eavConfig->getEntityType('customer_address')->getId(),
        'attribute_code' => DEP_ATTR_CODE,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'SdI Code',
        'is_user_defined' => 1,
        'is_visible' => 1,
        'is_required' => 1,
        'is_system' => 0,
        'used_in_forms' => ['customer_address_edit', 'adminhtml_customer_address'],
        'depends_on' => '[{"operator":"AND","condition":{"country_id":["IT"]}}]',
    ])->save();
    Mage::app()->cleanCache();
    $eavConfig->clear();
});

afterAll(function (): void {
    try {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $write->delete($resource->getTableName('customer/entity'), ['email LIKE ?' => DEP_ATTR_EMAIL_PREFIX . '%']);
        $attribute = Mage::getModel('customer/attribute')->loadByCode('customer_address', DEP_ATTR_CODE);
        if ($attribute->getId()) {
            $attribute->delete();
        }
        foreach (['sales/quote_address', 'sales/order_address'] as $table) {
            $table = $resource->getTableName($table);
            if ($write->tableColumnExists($table, DEP_ATTR_CODE)) {
                $write->dropColumn($table, DEP_ATTR_CODE);
            }
            $write->resetDdlCache($table);
        }
        Mage::app()->cleanCache();
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('Attribute dependencies (REST)', function (): void {

    it('lists the condition of the attribute', function (): void {
        $list = apiGet('/api/rest/v2/custom-attributes?entityType=customer_address', serviceToken(['custom-attributes/read']));
        $items = $list['json']['member'] ?? $list['json']['hydra:member'] ?? $list['json'];
        $attribute = array_values(array_filter($items, fn(array $item): bool => ($item['code'] ?? '') === DEP_ATTR_CODE))[0] ?? null;

        expect($list['status'])->toBe(200)
            ->and($attribute['dependsOn'] ?? null)->toBe([['operator' => 'AND', 'condition' => ['country_id' => ['IT']]]]);
    });

    it('gives the condition to a headless storefront in the address form', function (): void {
        $response = apiGet('/api/rest/v2/customer-forms/customer_address_edit');
        $fields = array_merge(...array_column($response['json']['groups'] ?? [], 'fields'));
        $field = $fields[array_search(DEP_ATTR_CODE, array_column($fields, 'code'), true)] ?? [];

        expect($response['status'])->toBe(200)
            ->and($field['dependsOn'] ?? null)->toBe([['operator' => 'AND', 'condition' => ['country_id' => ['IT']]]]);
    });

    it('requires the field for Italy only', function (): void {
        $customerId = depAttrCustomer();

        $italy = apiPost("/api/rest/v2/customers/{$customerId}/addresses", depAttrAddress('IT'), adminToken());
        $unitedStates = apiPost("/api/rest/v2/customers/{$customerId}/addresses", depAttrAddress('US'), adminToken());

        expect($italy['status'])->toBe(422)
            ->and(array_column($italy['json']['details']['errors'] ?? [], 'field'))->toContain('customAttributes.' . DEP_ATTR_CODE)
            ->and($unitedStates['status'])->toBeSuccessful();
    });

    it('does not keep a value for a hidden field', function (): void {
        $customerId = depAttrCustomer();

        $created = apiPost("/api/rest/v2/customers/{$customerId}/addresses", depAttrAddress('US', [DEP_ATTR_CODE => 'ABC1234']), adminToken());
        $read = apiGet('/api/rest/v2/addresses/' . (int) ($created['json']['id'] ?? 0), adminToken());

        expect($created['status'])->toBeSuccessful()
            ->and($read['json']['customAttributes'] ?? [])->not->toHaveKey(DEP_ATTR_CODE);
    });

});
