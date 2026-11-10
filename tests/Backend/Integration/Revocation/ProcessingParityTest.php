<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Put;
use Maho\Revocation\Api\RevocationRequest;
use Maho\Revocation\Api\RevocationRequestProcessor;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Accept and Reject buttons of the admin view and of the grid, and PUT /api/rest/v2/revocation-requests/{id} save the decision
 * of the merchant through Maho_Revocation_Service_Request::decide() (#1348). The model checks the status and stamps
 * processed_at, and the service adds a note to the matched order.
 */

function revocationProcessingFixture(bool $withOrder = false): Maho_Revocation_Model_Request
{
    $order = null;
    if ($withOrder) {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId((string) random_int(900000000, 999999999))
            ->setStoreId(1)
            ->setCustomerEmail('processing-' . uniqid() . '@example.com')
            ->setCustomerIsGuest()
            ->setBaseToGlobalRate(1)
            ->setBaseToOrderRate(1);
        $order->save();
        $GLOBALS['revocationProcessingCreated'][] = $order;
    }

    $request = Mage::getModel('revocation/request');
    $request->setStoreId(1)
        ->setOrderId($order ? (int) $order->getId() : null)
        ->setOrderReference($order ? (string) $order->getIncrementId() : 'PARITY-' . uniqid())
        ->setCustomerName('Max Mustermann')
        ->setEmail('processing-' . uniqid() . '@example.com')
        ->setVerified(0)
        ->setReceivedAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->save();
    $GLOBALS['revocationProcessingCreated'][] = $request;
    return $request;
}

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function revocationProcessingViaAdmin(int $id, string $decision): array
{
    return TransportParity::admin(Maho_Revocation_Adminhtml_Sales_RevocationController::class, 'process', [
        'id' => $id,
        'decision' => $decision,
    ]);
}

/**
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function revocationProcessingViaApi(Maho_Revocation_Model_Request $request, ?string $status, ?string $note = null): array
{
    $dto = RevocationRequest::fromModel($request);
    $dto->processedStatus = $status;
    $dto->adminNote = $note;
    $processor = new RevocationRequestProcessor(TransportParity::adminSecurity());
    return TransportParity::api(fn() => $processor->process($dto, new Put(), ['id' => (int) $request->getId()]));
}

/**
 * @return list<string>
 */
function revocationProcessingOrderComments(Maho_Revocation_Model_Request $request): array
{
    $comments = [];
    foreach (Mage::getModel('sales/order')->load($request->getOrderId())->getStatusHistoryCollection() as $history) {
        $comments[] = (string) $history->getComment();
    }
    return array_values(array_filter($comments));
}

beforeEach(function () {
    $GLOBALS['revocationProcessingCreated'] = [];
});

afterEach(function () {
    foreach (array_reverse($GLOBALS['revocationProcessingCreated']) as $model) {
        $model->delete();
    }
});

it('sets the status, stamps processed_at and notes the matched order on every transport', function (string $decision, string $status) {
    $viaAdmin = revocationProcessingFixture(withOrder: true);
    $viaMass = revocationProcessingFixture(withOrder: true);
    $viaApi = revocationProcessingFixture(withOrder: true);

    $admin = revocationProcessingViaAdmin((int) $viaAdmin->getId(), $decision);
    $mass = TransportParity::admin(Maho_Revocation_Adminhtml_Sales_RevocationController::class, 'mass' . ucfirst($decision), [
        'request' => [(int) $viaMass->getId()],
    ]);
    $api = revocationProcessingViaApi($viaApi, $status);

    expect($admin['errors'])->toBe([])
        ->and($mass['errors'])->toBe([])
        ->and($api['status'])->toBe(200);
    foreach ([$viaAdmin, $viaMass, $viaApi] as $request) {
        $reloaded = Mage::getModel('revocation/request')->load($request->getId());
        expect($reloaded->getProcessedStatus())->toBe($status)
            ->and($reloaded->getProcessedAt())->not->toBeNull()
            ->and(revocationProcessingOrderComments($request))
            ->toBe(["Revocation request #{$request->getId()} {$status}."]);
    }
})->with([
    'accept' => ['accept', 'accepted'],
    'reject' => ['reject', 'rejected'],
]);

it('notes the order once when a later save repeats the status', function () {
    $request = revocationProcessingFixture(withOrder: true);

    revocationProcessingViaAdmin((int) $request->getId(), 'accept');
    revocationProcessingViaApi(Mage::getModel('revocation/request')->load($request->getId()), 'accepted', 'Refund sent');

    expect(revocationProcessingOrderComments($request))->toHaveCount(1)
        ->and(Mage::getModel('revocation/request')->load($request->getId())->getAdminNote())->toBe('Refund sent');
});

it('keeps processed_at when a later save does not change the status', function () {
    $request = revocationProcessingFixture();
    revocationProcessingViaAdmin((int) $request->getId(), 'accept');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('revocation/request'),
        ['processed_at' => '2026-01-01 00:00:00'],
        ['request_id = ?' => (int) $request->getId()],
    );

    revocationProcessingViaApi(Mage::getModel('revocation/request')->load($request->getId()), 'accepted', 'Later note');

    expect(Mage::getModel('revocation/request')->load($request->getId())->getProcessedAt())->toBe('2026-01-01 00:00:00');
});

it('reports a missing request on both transports', function () {
    $admin = revocationProcessingViaAdmin(999999999, 'accept');
    $api = TransportParity::api(fn() => new RevocationRequestProcessor(TransportParity::adminSecurity())
        ->process(new RevocationRequest(), new Put(), ['id' => 999999999]));

    expect($admin['errors'])->toBe(['This revocation request no longer exists.'])
        ->and($api['status'])->toBe(404)
        ->and($api['message'])->toBe('Revocation request not found');
});

describe('kept difference: the admin view offers only accept and reject', function () {
    it('refuses an unknown status in the API, and the admin save action changes only the note', function () {
        $viaAdmin = revocationProcessingFixture(withOrder: true);
        $viaApi = revocationProcessingFixture(withOrder: true);

        $admin = TransportParity::admin(Maho_Revocation_Adminhtml_Sales_RevocationController::class, 'save', [
            'id' => $viaAdmin->getId(),
            'processed_status' => 'accepted',
            'admin_note' => '  A note  ',
        ]);
        $api = revocationProcessingViaApi($viaApi, 'refunded', 'A note');

        expect($admin['errors'])->toBe([])
            ->and($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['processedStatus']);
        $reloadedAdmin = Mage::getModel('revocation/request')->load($viaAdmin->getId());
        expect($reloadedAdmin->getProcessedStatus())->toBeNull()
            ->and($reloadedAdmin->getAdminNote())->toBe('A note')
            ->and(revocationProcessingOrderComments($viaAdmin))->toBe([]);
        $reloadedApi = Mage::getModel('revocation/request')->load($viaApi->getId());
        expect($reloadedApi->getProcessedStatus())->toBeNull()
            ->and($reloadedApi->getAdminNote())->toBeNull()
            ->and(revocationProcessingOrderComments($viaApi))->toBe([]);
    });
});
