<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function customerRestResource(mixed $filter = null): Mage_Customer_Model_Api2_Customer_Rest_Admin_V1
{
    $request = new class extends Mage_Api2_Model_Request {
        public mixed $testFilter = null;

        #[\Override]
        public function getFilter()
        {
            return $this->testFilter;
        }
    };
    $request->testFilter = $filter;

    $resource = new Mage_Customer_Model_Api2_Customer_Rest_Admin_V1();
    $resource->setRequest($request);
    return $resource;
}

function callCustomerRest(Mage_Customer_Model_Api2_Customer_Rest_Admin_V1 $resource, string $method, mixed ...$arguments): mixed
{
    return (fn(mixed ...$args): mixed => $this->{$method}(...$args))->call($resource, ...$arguments);
}

it('removes the credentials and the confirmation key from a REST customer response', function (): void {
    $data = callCustomerRest(customerRestResource(), '_prepareCustomerDataForResponse', [
        'entity_id' => 5,
        'email' => 'john@example.com',
        'password_hash' => 'hash:salt',
        'rp_token' => 'reset-token',
        'rp_token_created_at' => '2026-01-01 00:00:00',
        'rp_customer_id' => 'reset-id',
        'confirmation' => 'confirmation-key',
    ]);

    expect($data)->toBe([
        'entity_id' => 5,
        'email' => 'john@example.com',
        'confirmation' => true,
    ]);
});

it('refuses a REST collection filter that can find a credential or the confirmation key', function (array $filter): void {
    callCustomerRest(customerRestResource([$filter]), '_validateSecretFilters');
})->throws(Mage_Api2_Exception::class)->with([
    'password hash' => [['attribute' => 'password_hash', 'like' => '%']],
    'reset token in upper case' => [['attribute' => ' RP_TOKEN ', 'eq' => 'x']],
    'confirmation key value' => [['attribute' => 'confirmation', 'eq' => 'confirmation-key']],
]);

it('accepts a REST collection filter on a pending confirmation or a plain attribute', function (array $filter): void {
    callCustomerRest(customerRestResource([$filter]), '_validateSecretFilters');
    expect(true)->toBeTrue();
})->with([
    'pending confirmation' => [['attribute' => 'confirmation', 'notnull' => true]],
    'email' => [['attribute' => 'email', 'like' => '%@example.com']],
]);
