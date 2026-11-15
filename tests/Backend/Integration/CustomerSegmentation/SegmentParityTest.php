<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Maho\CustomerSegmentation\Api\CustomerSegmentProcessor;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The segment admin page and the customer segment API save and refresh a segment through
 * Maho_CustomerSegmentation_Service_Segment (#1348). Each test gives the same input to both transports.
 */

const SEGMENT_PARITY_PREFIX = 'Pest parity ';

function segmentParityService(): Maho_CustomerSegmentation_Service_Segment
{
    /** @var Maho_CustomerSegmentation_Service_Segment */
    return Mage::getService('customersegmentation/segment');
}

/**
 * Save the fields $segmentPost of the segment form, as the admin page posts them.
 *
 * @param array<string, mixed> $segmentPost
 * @return array{success: list<string>, errors: list<string>}
 */
function segmentParityAdmin(array $segmentPost): array
{
    return AdminApiParity::admin(
        Maho_CustomerSegmentation_Adminhtml_CustomerSegmentation_IndexController::class,
        'save',
        ['segment' => $segmentPost],
    );
}

/**
 * Give $segment to the processor of the API with the operation $operationName, as the API does after it maps the body.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function segmentParityApi(Maho_CustomerSegmentation_Model_Segment $segment, string $operationName = 'create_customer_segment'): array
{
    $request = AdminApiParity::jsonRequest('POST', '/api/rest/v2/customer-segments');
    return AdminApiParity::api($request, fn() => new CustomerSegmentProcessor(AdminApiParity::adminSecurity())
        ->process($segment, new Post(name: $operationName), [], ['request' => $request]));
}

function segmentParityByName(string $name): Maho_CustomerSegmentation_Model_Segment
{
    return Mage::getModel('customersegmentation/segment')->load($name, 'name');
}

function segmentParityCleanup(): void
{
    Mage::getResourceModel('customersegmentation/segment_collection')
        ->addFieldToFilter('name', ['like' => SEGMENT_PARITY_PREFIX . '%'])
        ->walk('delete');
}

beforeEach(function (): void {
    $this->admin = AdminRoleFixture::login(['all']);
    segmentParityCleanup();
});

afterEach(function (): void {
    segmentParityCleanup();
    AdminRoleFixture::delete($this->admin);
});

it('reports the same problems of a segment on the admin page and in the API, and saves nothing', function (): void {
    $admin = segmentParityAdmin(['name' => '', 'website_ids' => [999999], 'refresh_mode' => 'often']);
    $api = segmentParityApi(
        segmentParityService()->newSegment()->setName('')->setWebsiteIds([999999])->setRefreshMode('often'),
    );

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['name', 'refreshMode', 'websiteIds'])
        ->and($admin['errors'])->toBe($api['errors'])
        ->and($admin['success'])->toBe([]);
});

it('saves the same segment from the admin page and the API', function (): void {
    $websiteId = (int) Mage::app()->getWebsite(true)->getId();
    $admin = segmentParityAdmin([
        'name' => ' ' . SEGMENT_PARITY_PREFIX . 'admin ',
        'website_ids' => [$websiteId],
        'customer_group_ids' => [1],
        'refresh_mode' => 'manual',
        'is_active' => 1,
    ]);
    $api = segmentParityApi(
        segmentParityService()->newSegment()
            ->setName(' ' . SEGMENT_PARITY_PREFIX . 'api ')
            ->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([1])
            ->setRefreshMode('manual')
            ->setIsActive(true),
    );

    $fromAdmin = segmentParityByName(SEGMENT_PARITY_PREFIX . 'admin');
    $fromApi = segmentParityByName(SEGMENT_PARITY_PREFIX . 'api');
    $fields = static fn(Maho_CustomerSegmentation_Model_Segment $segment): array => [
        $segment->getWebsiteIds(), $segment->getCustomerGroupIds(), $segment->getRefreshMode(), $segment->getIsActive(), $segment->getPriority(),
    ];

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200)
        ->and($fromAdmin->getId())->not->toBeNull()
        ->and($fromApi->getId())->not->toBeNull()
        ->and($fields($fromAdmin))->toBe($fields($fromApi));
});

it('answers a conflict at once when the API refreshes a segment that another refresh holds', function (): void {
    $segment = segmentParityService()->save(
        segmentParityService()->newSegment()
            ->setName(SEGMENT_PARITY_PREFIX . 'refresh')
            ->setWebsiteIds([(int) Mage::app()->getWebsite(true)->getId()]),
    );
    $lock = Mage::getSingleton('core/lock');
    $lockName = 'customersegmentation_refresh_' . (int) $segment->getId();
    expect($lock->acquire($lockName))->toBeTrue();

    $startedAt = microtime(true);
    try {
        $api = segmentParityApi($segment, 'refresh_customer_segment');
    } finally {
        $lock->release($lockName);
    }
    $free = segmentParityApi($segment, 'refresh_customer_segment');

    // A wait for the lock lasts REFRESH_WAIT_SECONDS (an hour). The margin covers a slow or busy CI runner.
    expect($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['Another refresh of the segment is in progress.'])
        ->and(microtime(true) - $startedAt)->toBeLessThan(10.0)
        ->and($free['status'])->toBe(200);
});
