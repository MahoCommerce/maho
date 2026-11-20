<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function apiSecretPaymentData(): array
{
    return [
        'method' => 'ccsave',
        'cc_type' => 'VI',
        'cc_last_4' => '1111',
        'cc_owner' => 'John Doe',
        'cc_trans_id' => 'TX-1',
        'cc_number' => '4111111111111111',
        'cc_number_enc' => 'encrypted-number',
        'cc_cid' => '123',
        'cc_cid_enc' => 'encrypted-cid',
        'cc_debug_request_body' => 'request with card',
        'cc_debug_response_body' => 'response with card',
        'cc_debug_response_serialized' => 'serialized response',
        'password_hash' => 'hash:salt',
        'additional_information' => [
            'paypal_order_id' => 'ORDER-1',
            'secure_silent_post_hash' => 'silent-post-secret',
            'secure_token' => 'token',
            'secure_token_id' => 'token-id',
        ],
    ];
}

function expectNoApiPaymentSecrets(array $result): void
{
    foreach (['cc_number', 'cc_number_enc', 'cc_cid', 'cc_cid_enc', 'cc_debug_request_body', 'cc_debug_response_body', 'cc_debug_response_serialized', 'password_hash'] as $field) {
        expect($result)->not->toHaveKey($field);
    }
    expect($result['additional_information'])->toBe(['paypal_order_id' => 'ORDER-1'])
        ->and($result['cc_type'])->toBe('VI')
        ->and($result['cc_last_4'])->toBe('1111')
        ->and($result['cc_owner'])->toBe('John Doe')
        ->and($result['cc_trans_id'])->toBe('TX-1');
}

it('removes card data, debug payloads, password hashes and payment secrets from API data', function (): void {
    $resource = new class extends Mage_Api_Model_Resource_Abstract {
        public function removeSecrets(array $data): array
        {
            return $this->_removeSecrets($data);
        }
    };

    expectNoApiPaymentSecrets($resource->removeSecrets(apiSecretPaymentData()));
});

it('returns no secret from an order API read', function (): void {
    $resource = new class extends Mage_Sales_Model_Api_Resource {
        public function read(Maho\DataObject $object): array
        {
            return $this->_getAttributes($object, 'order_payment');
        }
    };

    expectNoApiPaymentSecrets($resource->read(new Maho\DataObject(apiSecretPaymentData())));
});

it('returns no secret from a cart API read', function (): void {
    $resource = new class extends Mage_Checkout_Model_Api_Resource {
        public function read(Maho\DataObject $object): array
        {
            return $this->_getAttributes($object, 'quote_payment');
        }
    };

    expectNoApiPaymentSecrets($resource->read(new Maho\DataObject(apiSecretPaymentData())));
});
