<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Revocation order statuses', function () {
    it('seeds revocation_accepted and revocation_rejected under the complete state', function () {
        $statuses = Mage::getResourceModel('sales/order_status_collection')->toOptionHash();
        expect($statuses)->toHaveKey(Maho_Revocation_Model_Request::ORDER_STATUS_ACCEPTED);
        expect($statuses)->toHaveKey(Maho_Revocation_Model_Request::ORDER_STATUS_REJECTED);

        $completeStatuses = Mage::getSingleton('sales/order_config')
            ->getStateStatuses(Mage_Sales_Model_Order::STATE_COMPLETE, false);
        expect($completeStatuses)->toContain(Maho_Revocation_Model_Request::ORDER_STATUS_ACCEPTED);
        expect($completeStatuses)->toContain(Maho_Revocation_Model_Request::ORDER_STATUS_REJECTED);
    });

    it('applies the revocation_accepted status without changing the order state', function () {
        $order = Mage::getModel('sales/order');
        $order->setIncrementId((string) random_int(900000000, 999999999));
        $order->setStoreId(1);
        $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
        $order->setStatus('complete');
        $order->setCustomerEmail('status-test-' . uniqid() . '@example.com');
        $order->setCustomerIsGuest(1);
        $order->setBaseToGlobalRate(1);
        $order->setBaseToOrderRate(1);
        $order->save();

        $history = $order->addStatusHistoryComment(
            'Revocation request #42 accepted.',
            Maho_Revocation_Model_Request::ORDER_STATUS_ACCEPTED,
        );
        $history->setIsCustomerNotified(false);
        $order->save();

        $reloaded = Mage::getModel('sales/order')->load($order->getId());
        expect($reloaded->getStatus())->toBe(Maho_Revocation_Model_Request::ORDER_STATUS_ACCEPTED);
        expect($reloaded->getState())->toBe(Mage_Sales_Model_Order::STATE_COMPLETE);

        $order->delete();
    });
});

describe('Revocation suppressed-email handling', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore();
        $store->setConfig('revocation/general/enabled', '1');
        $store->setConfig('trans_email/ident_general/email', 'shop@example.com');
        $store->setConfig('trans_email/ident_general/name', 'Test Shop');
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
