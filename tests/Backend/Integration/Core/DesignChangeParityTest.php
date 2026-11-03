<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Core\Api\DesignChange;
use Mage\Core\Api\DesignChangeProcessor;
use Mage\Core\Api\DesignChangeProvider;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page and the API save a design change through Mage_Core_Service_Design (#1348).
 * Each test gives the same change to both transports and compares the outcome.
 */

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function designChangeViaAdmin(array $fields, ?int $id = null): array
{
    return AdminApiParity::admin(Mage_Adminhtml_System_DesignController::class, 'save', ['design' => $fields] + ($id ? ['id' => $id] : []));
}

/**
 * @param array<string, mixed> $body the JSON body
 * @param list<int>|null $allowedStoreIds
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function designChangeViaApi(array $body, ?int $id = null, ?array $allowedStoreIds = null): array
{
    $request = AdminApiParity::jsonRequest($id ? 'PUT' : 'POST', '/api/rest/v2/design-changes' . ($id ? "/{$id}" : ''), $body);
    $dto = new DesignChange();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    return AdminApiParity::api($request, fn() => new DesignChangeProcessor(AdminApiParity::adminSecurity($allowedStoreIds))
        ->process($dto, $id ? new Put() : new Post(), $id ? ['id' => $id] : [], ['request' => $request]));
}

/**
 * @return list<int> the IDs of the changes of $storeId in the year 2095
 */
function designChangeIds(int $storeId): array
{
    return array_map(intval(...), Mage::getResourceModel('core/design_collection')
        ->addFieldToFilter('store_id', $storeId)
        ->addFieldToFilter('date_from', ['gteq' => '2095-01-01'])
        ->getAllIds());
}

beforeEach(function () {
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    foreach (designChangeIds($this->storeId) as $id) {
        Mage::getModel('core/design')->load($id)->delete();
    }
});

afterEach(function () {
    foreach (designChangeIds($this->storeId) as $id) {
        Mage::getModel('core/design')->load($id)->delete();
    }
});

it('saves the same change on both transports', function () {
    $admin = designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default', 'date_from' => '2095-01-01', 'date_to' => '2095-01-31']);
    $api = designChangeViaApi(['storeId' => $this->storeId, 'design' => 'base/default', 'dateFrom' => '2095-03-01', 'dateTo' => '2095-03-31']);

    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The design change has been saved.'])
        ->and($api['status'])->toBe(200)
        ->and($api['result']->dateFrom)->toBe('2095-03-01')
        ->and(designChangeIds($this->storeId))->toHaveCount(2);
});

it('rejects an overlap with another change of the store view on both transports', function () {
    designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default', 'date_from' => '2095-01-01', 'date_to' => '2095-01-31']);

    $message = 'Your design change for the specified store intersects with another one, please specify another date range.';
    $admin = designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default', 'date_from' => '2095-01-15', 'date_to' => '2095-02-15']);
    $api = designChangeViaApi(['storeId' => $this->storeId, 'design' => 'base/default', 'dateFrom' => '2095-01-15', 'dateTo' => '2095-02-15']);

    expect($admin['errors'])->toBe([$message])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe([$message]);
});

it('gives the same field errors on both transports', function () {
    $admin = designChangeViaAdmin(['store_id' => '', 'design' => 'base/no_such_theme', 'date_from' => '2095-02-01', 'date_to' => '2095-01-01']);
    $api = designChangeViaApi(['design' => 'base/no_such_theme', 'dateFrom' => '2095-02-01', 'dateTo' => '2095-01-01']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['storeId', 'design', 'dateFrom'])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('rejects a date that is not a date on both transports', function () {
    $admin = designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default', 'date_from' => 'not-a-date']);
    $api = designChangeViaApi(['storeId' => $this->storeId, 'design' => 'base/default', 'dateFrom' => 'not-a-date']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['dateFrom'])
        ->and($admin['errors'])->toBe($api['errors']);
});

it('answers a missing change as not found on both transports', function () {
    $admin = designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default'], 999999999);
    $api = designChangeViaApi(['design' => 'base/default'], 999999999);

    expect($admin['errors'])->toBe(['This design change no longer exists.'])
        ->and($api['status'])->toBe(404);
});

it('hides a change of another store view from a store-restricted token', function () {
    designChangeViaAdmin(['store_id' => $this->storeId, 'design' => 'base/default', 'date_from' => '2095-01-01', 'date_to' => '2095-01-31']);
    $id = designChangeIds($this->storeId)[0];

    $request = AdminApiParity::jsonRequest('DELETE', "/api/rest/v2/design-changes/{$id}");
    $delete = AdminApiParity::api($request, fn() => new DesignChangeProcessor(AdminApiParity::adminSecurity([-1]))
        ->process(null, new Delete(), ['id' => $id], ['request' => $request]));
    $read = new DesignChangeProvider(AdminApiParity::adminSecurity([-1]))->provide(new Get(class: DesignChange::class, name: 'get_design_change'), ['id' => $id]);

    expect($delete['status'])->toBe(404)
        ->and($read)->toBeNull()
        ->and(designChangeIds($this->storeId))->toBe([$id]);
});
