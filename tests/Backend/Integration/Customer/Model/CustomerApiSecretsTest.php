<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->email = 'api-secrets-' . uniqid() . '@example.com';
    $this->customer = Mage::getModel('customer/customer')->setWebsiteId(1)->addData([
        'email' => $this->email,
        'firstname' => 'Api',
        'lastname' => 'Secrets',
    ]);
    $this->customer->setPassword('Secret-Pass-123');
    $this->customer->save();
    $this->customer->setConfirmation('confirmation-key-123')
        ->setRpToken('reset-token-123')
        ->setRpTokenCreatedAt('2026-01-01 00:00:00')
        ->save();
    $this->api = Mage::getModel('customer/customer_api');
});

afterEach(function (): void {
    if ($this->customer->getId()) {
        $this->customer->delete();
    }
});

function expectNoCustomerApiSecrets(array $data): void
{
    foreach (['password_hash', 'rp_token', 'rp_token_created_at', 'rp_customer_id'] as $code) {
        expect($data)->not->toHaveKey($code);
    }
    expect($data['confirmation'])->toBeTrue();
}

it('returns no credential and no confirmation key from customer.info', function (): void {
    $info = $this->api->info($this->customer->getId());

    expect($info['email'])->toBe($this->email);
    expectNoCustomerApiSecrets($info);
});

it('returns no credential even when customer.info names it', function (): void {
    $info = $this->api->info($this->customer->getId(), ['email', 'password_hash', 'rp_token']);

    expect($info)->not->toHaveKey('password_hash')
        ->and($info)->not->toHaveKey('rp_token')
        ->and($info['email'])->toBe($this->email);
});

it('returns no credential and no confirmation key from customer.list', function (): void {
    $items = $this->api->items(['email' => $this->email]);

    expect($items)->toHaveCount(1);
    expectNoCustomerApiSecrets($items[0]);
});

it('refuses a customer.list filter on a credential', function (string $field): void {
    $this->api->items([$field => ['like' => '%']]);
})->throws(Mage_Api_Exception::class)->with(['password_hash', 'rp_token', 'PASSWORD_HASH']);

it('refuses a customer.list filter on the value of the confirmation key', function (): void {
    $this->api->items(['confirmation' => ['eq' => 'confirmation-key-123']]);
})->throws(Mage_Api_Exception::class);

it('refuses a customer.list filter on the attribute ID of a credential', function (): void {
    $attributeId = Mage::getSingleton('eav/config')->getAttribute('customer', 'password_hash')->getId();
    $this->api->items([(string) $attributeId => ['like' => '%']]);
})->throws(Mage_Api_Exception::class);

it('accepts a customer.list filter on a pending confirmation', function (): void {
    $items = $this->api->items(['email' => $this->email, 'confirmation' => ['notnull' => true]]);

    expect($items)->toHaveCount(1);
});

it('never saves a written boolean as the confirmation key', function (): void {
    $this->api->update($this->customer->getId(), ['confirmation' => true]);
    expect(Mage::getModel('customer/customer')->load($this->customer->getId())->getConfirmation())->toBe('confirmation-key-123');

    $this->api->update($this->customer->getId(), ['confirmation' => 'false']);
    expect(Mage::getModel('customer/customer')->load($this->customer->getId())->getConfirmation())->toBeNull();
});
