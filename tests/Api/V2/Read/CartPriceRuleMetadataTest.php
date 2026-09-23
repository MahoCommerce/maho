<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 condition metadata of cart price rules.
 *
 * @group read
 */

const CPR_METADATA_PATH = '/api/rest/v2/cart-price-rules/condition-metadata';

afterAll(function (): void {
    cleanupTestData();
});

describe('Cart price rule condition metadata', function (): void {

    it('describes the trees, the rule options and the scope', function (): void {
        $response = apiGet(CPR_METADATA_PATH, adminToken());

        expect($response['status'])->toBe(200);
        $json = $response['json'];
        expect($json['version'])->toMatch('/^[0-9a-f]{64}$/')
            ->and($json['locale'])->toBeString()
            ->and($json['roots'])->toBe([
                'conditions' => 'salesrule/rule_condition_combine',
                'actions' => 'salesrule/rule_condition_product_combine',
            ]);

        foreach ($json['types'] as $description) {
            foreach ($description['children'] ?? [] as $child) {
                foreach ($child['options'] ?? [$child] as $entry) {
                    expect($json['types'])->toHaveKey($entry['type']);
                }
            }
        }
        $address = array_column($json['types']['salesrule/rule_condition_address']['attributes'], null, 'code');
        expect($address['base_subtotal']['inputType'])->toBe('numeric')
            ->and($address['region_id']['options']['paged'])->toBeTrue()
            ->and($address['region_id']['options']['inline'])->toBeNull();

        expect(array_column($json['rule']['simpleAction'], 'value'))->toBe(['by_percent', 'by_fixed', 'cart_fixed', 'buy_x_get_y'])
            ->and(array_column($json['rule']['couponType'], 'value'))->toBe(['none', 'specific', 'auto'])
            ->and(array_column($json['rule']['couponFormats'], 'value'))->toContain($json['rule']['couponDefaults']['format'])
            ->and($json['rule']['couponDefaults']['length'])->toBeInt();

        expect(array_column($json['scope']['websites'], 'id'))->toContain(1)
            ->and(array_column($json['scope']['customerGroups'], 'id'))->toContain(0);
    });

    it('lists the customer segment condition when the module is active', function (): void {
        if (!Mage::helper('core')->isModuleEnabled('Maho_CustomerSegmentation')) {
            $this->markTestSkipped('Maho_CustomerSegmentation is not active');
        }
        $json = apiGet(CPR_METADATA_PATH, adminToken())['json'];

        expect($json['types'])->toHaveKey('customersegmentation/rule_condition_segment');
        $children = array_column($json['types']['salesrule/rule_condition_combine']['children'], 'type');
        expect($children)->toContain('customersegmentation/rule_condition_segment');
    });

    it('answers with only the version and the scope when the known version is current', function (): void {
        $token = adminToken();
        $version = apiGet(CPR_METADATA_PATH, $token)['json']['version'];

        $unchanged = apiGet(CPR_METADATA_PATH . '?knownVersion=' . $version, $token);
        expect($unchanged['status'])->toBe(200)
            ->and($unchanged['json']['version'])->toBe($version)
            ->and($unchanged['json']['unchanged'])->toBeTrue()
            ->and($unchanged['json'])->toHaveKey('scope')
            ->and($unchanged['json'])->not->toHaveKey('types');

        $changed = apiGet(CPR_METADATA_PATH . '?knownVersion=old', $token);
        expect($changed['json'])->toHaveKey('types')
            ->and($changed['json'])->not->toHaveKey('unchanged');
    });

    it('limits the scope to the websites and stores of a restricted token', function (): void {
        $response = apiGet(CPR_METADATA_PATH, serviceToken(['cart-price-rules/read'], [1]));

        expect($response['status'])->toBe(200)
            ->and(array_column($response['json']['scope']['websites'], 'id'))->toBe([1])
            ->and(array_column($response['json']['scope']['stores'], 'id'))->toBe([1]);
    });

    it('needs the read grant of cart price rules', function (): void {
        expect(apiGet(CPR_METADATA_PATH)['status'])->toBe(401)
            ->and(apiGet(CPR_METADATA_PATH, customerToken())['status'])->toBe(403)
            ->and(apiGet(CPR_METADATA_PATH, serviceToken(['coupons/read']))['status'])->toBe(403)
            ->and(apiGet(CPR_METADATA_PATH, serviceToken(['cart-price-rules/read']))['status'])->toBe(200);
    });

    it('denies an admin whose role does not include cart price rules', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_cpr_meta_acl_' . substr(uniqid(), -6));
        expect(apiGet(CPR_METADATA_PATH, $token)['status'])->toBe(403);
    });

    it('gives the labels in the interface locale of the admin user', function (): void {
        $username = 'pest_cpr_locale_' . substr(uniqid(), -6);
        $token = adminTokenWithAcl(['all'], $username);
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $write->update($resource->getTableName('admin/user'), ['backend_locale' => 'it_IT'], ['username = ?' => $username]);
        $string = 'equals or greater than';
        $write->insert($resource->getTableName('core/translate'), [
            'string' => $string,
            'store_id' => 0,
            'translate' => 'uguale o maggiore di',
            'locale' => 'it_IT',
            'crc_string' => crc32($string),
        ]);
        Mage::app()->getCache()->remove('API_CART_PRICE_RULE_CONDITION_METADATA_it_IT');
        Mage::app()->getCache()->clean([Mage_Core_Model_Translate::CACHE_TAG]);

        try {
            $json = apiGet(CPR_METADATA_PATH, $token)['json'];
            $address = array_column($json['types']['salesrule/rule_condition_address']['attributes'], null, 'code');
            $operators = array_column($address['base_subtotal']['operators'], 'label', 'value');

            expect($json['locale'])->toBe('it_IT')
                ->and($operators['>='])->toBe('uguale o maggiore di');
        } finally {
            $write->delete($resource->getTableName('core/translate'), ['locale = ?' => 'it_IT', 'string = ?' => $string]);
            Mage::app()->getCache()->remove('API_CART_PRICE_RULE_CONDITION_METADATA_it_IT');
            Mage::app()->getCache()->clean([Mage_Core_Model_Translate::CACHE_TAG]);
        }
    });

});
