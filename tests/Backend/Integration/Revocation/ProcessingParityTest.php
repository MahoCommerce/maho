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
 * The admin view and PUT /api/rest/v2/revocation-requests/{id} save the decision of the merchant through
 * Maho_Revocation_Service_Request (#1348). The model checks the status and stamps processed_at.
 */

function revocationProcessingFixture(): Maho_Revocation_Model_Request
{
    $request = Mage::getModel('revocation/request');
    $request->setStoreId(1)
        ->setOrderReference('PARITY-' . uniqid())
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
function revocationProcessingViaAdmin(int $id, string $status, string $note): array
{
    return TransportParity::admin(Maho_Revocation_Adminhtml_Sales_RevocationController::class, 'save', [
        'id' => $id,
        'processed_status' => $status,
        'admin_note' => $note,
    ]);
}

/**
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function revocationProcessingViaApi(Maho_Revocation_Model_Request $request, string $status, string $note): array
{
    $dto = RevocationRequest::fromModel($request);
    $dto->processedStatus = $status;
    $dto->adminNote = $note;
    $processor = new RevocationRequestProcessor(TransportParity::adminSecurity());
    return TransportParity::api(fn() => $processor->process($dto, new Put(), ['id' => (int) $request->getId()]));
}

beforeEach(function () {
    $GLOBALS['revocationProcessingCreated'] = [];
});

afterEach(function () {
    foreach ($GLOBALS['revocationProcessingCreated'] as $request) {
        $request->delete();
    }
});

it('sets the status, stamps processed_at and trims the note on both transports', function () {
    $viaAdmin = revocationProcessingFixture();
    $viaApi = revocationProcessingFixture();

    $admin = revocationProcessingViaAdmin((int) $viaAdmin->getId(), 'accepted', '  Refund sent  ');
    $api = revocationProcessingViaApi($viaApi, 'accepted', '  Refund sent  ');

    expect($admin['errors'])->toBe([])
        ->and($api['status'])->toBe(200);
    foreach ([$viaAdmin, $viaApi] as $request) {
        $reloaded = Mage::getModel('revocation/request')->load($request->getId());
        expect($reloaded->getProcessedStatus())->toBe('accepted')
            ->and($reloaded->getProcessedAt())->not->toBeNull()
            ->and($reloaded->getAdminNote())->toBe('Refund sent');
    }
});

it('refuses an unknown status and saves nothing on both transports', function () {
    $viaAdmin = revocationProcessingFixture();
    $viaApi = revocationProcessingFixture();

    $admin = revocationProcessingViaAdmin((int) $viaAdmin->getId(), 'refunded', 'A note');
    $api = revocationProcessingViaApi($viaApi, 'refunded', 'A note');

    expect($admin['errors'])->toBe(['Invalid processing status.'])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['processedStatus']);
    foreach ([$viaAdmin, $viaApi] as $request) {
        $reloaded = Mage::getModel('revocation/request')->load($request->getId());
        expect($reloaded->getProcessedStatus())->toBeNull()
            ->and($reloaded->getAdminNote())->toBeNull();
    }
});

it('keeps processed_at when a later save does not change the status', function () {
    $request = revocationProcessingFixture();
    revocationProcessingViaAdmin((int) $request->getId(), 'accepted', '');
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
    $admin = revocationProcessingViaAdmin(999999999, 'accepted', '');
    $api = TransportParity::api(fn() => new RevocationRequestProcessor(TransportParity::adminSecurity())
        ->process(new RevocationRequest(), new Put(), ['id' => 999999999]));

    expect($admin['errors'])->toBe(['This revocation request no longer exists.'])
        ->and($api['status'])->toBe(404)
        ->and($api['message'])->toBe('Revocation request not found');
});
