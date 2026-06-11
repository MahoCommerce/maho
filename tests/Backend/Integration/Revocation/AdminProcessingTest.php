<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

describe('Revocation processing', function () {
    it('records the decision and notes the order without changing its status or state', function () {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId((string) random_int(900000000, 999999999));
        $order->setStoreId(1);
        $order->setData('state', Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->setStatus('processing');
        $order->setCustomerEmail('proc-' . uniqid() . '@example.com');
        $order->setCustomerIsGuest(1);
        $order->setBaseToGlobalRate(1);
        $order->setBaseToOrderRate(1);
        $order->save();

        $request = Mage::getModel('revocation/request');
        $request->setStoreId(1)
            ->setOrderId((int) $order->getId())
            ->setOrderReference($order->getIncrementId())
            ->setCustomerName('Max Mustermann')
            ->setEmail($order->getCustomerEmail())
            ->setVerified(0)
            ->setReceivedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->save();

        $httpRequest = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/admin/sales_revocation/process', 'POST', [
                'id' => $request->getId(),
                'decision' => 'accept',
            ]),
        );
        (new Maho_Revocation_Adminhtml_Sales_RevocationController(
            $httpRequest,
            new Mage_Core_Controller_Response_Http(),
        ))->processAction();

        $reloadedRequest = Mage::getModel('revocation/request')->load($request->getId());
        expect($reloadedRequest->getProcessedStatus())->toBe(Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED);

        // The outcome lives on the request; the order must never be pushed into a
        // revocation_* status or state (the coupling we deliberately removed).
        $reloadedOrder = Mage::getModel('sales/order')->load($order->getId());
        expect($reloadedOrder->getStatus())->not->toBe('revocation_accepted');
        expect($reloadedOrder->getState())->not->toBe('revocation_accepted');

        $comments = [];
        foreach ($reloadedOrder->getStatusHistoryCollection() as $history) {
            $comments[] = (string) $history->getComment();
        }
        expect(implode("\n", $comments))->toContain('accepted');

        $request->delete();
        $order->delete();
    });
});

describe('Revocation suppressed-email handling', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore();
        $store->setConfig('revocation/general/enabled', '1');
        $store->setConfig('trans_email/ident_general/email', 'shop@example.com');
        $store->setConfig('trans_email/ident_general/name', 'Test Shop');
        // No MTA on CI runners: disable the transport so send() reports success without dispatching.
        $store->setConfig('system/smtp/enabled', '');
    });

    it('clears suppressed_at and records a note when the admin resends the receipt', function () {
        $request = Mage::getModel('revocation/request');
        $request->setStoreId(1)
            ->setOrderReference('100000001')
            ->setCustomerName('Max Mustermann')
            ->setEmail('resend-' . uniqid() . '@example.com')
            ->setVerified(0)
            ->setReceivedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setSuppressedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setSuppressedReason(Maho_Revocation_Model_Request::SUPPRESSED_REASON_RATE_LIMIT)
            ->save();

        $sent = Mage::getModel('revocation/service')->resendReceipt($request);
        expect($sent)->toBeTrue();

        $reloaded = Mage::getModel('revocation/request')->load($request->getId());
        expect($reloaded->getSuppressedAt())->toBeNull();
        expect($reloaded->getSuppressedReason())->toBeNull();
        expect((string) $reloaded->getAdminNote())->toContain('Receipt email resent to');

        $request->delete();
    });
});
