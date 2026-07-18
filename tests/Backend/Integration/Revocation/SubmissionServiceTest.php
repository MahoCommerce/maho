<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function revocation_create_order(array $data = []): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId($data['increment_id'] ?? (string) random_int(900000000, 999999999));
    $order->setStoreId(1);
    $order->setData('state', $data['state'] ?? Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->setStatus($data['status'] ?? 'complete');
    $order->setCustomerEmail($data['customer_email'] ?? 'order-' . uniqid() . '@example.com');
    $order->setCustomerFirstname($data['customer_firstname'] ?? 'Max');
    $order->setCustomerLastname($data['customer_lastname'] ?? 'Mustermann');
    $order->setCustomerIsGuest(empty($data['customer_id']) ? 1 : 0);
    if (!empty($data['customer_id'])) {
        $order->setCustomerId($data['customer_id']);
    }
    $order->setGrandTotal(100.00);
    $order->setBaseGrandTotal(100.00);
    $order->setBaseToGlobalRate(1);
    $order->setBaseToOrderRate(1);

    $billing = Mage::getModel('sales/order_address');
    $billing->setAddressType('billing');
    $billing->setFirstname($data['customer_firstname'] ?? 'Max');
    $billing->setLastname($data['customer_lastname'] ?? 'Mustermann');
    $billing->setStreet('Musterstr. 1');
    $billing->setCity('Berlin');
    $billing->setPostcode('10115');
    $billing->setCountryId('DE');
    $billing->setTelephone('030123456');
    $order->setBillingAddress($billing);

    $order->save();
    return $order;
}

function revocation_create_customer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setEmail('customer-' . uniqid() . '@example.com');
    $customer->setFirstname('Max');
    $customer->setLastname('Mustermann');
    $customer->save();
    return $customer;
}

function revocation_submit_input(array $overrides = []): \Maho\DataObject
{
    return new \Maho\DataObject(array_merge([
        'customer_name' => 'Max Mustermann',
        'email' => 'cust-' . uniqid() . '@example.com',
        'order_reference' => (string) random_int(100000000, 199999999),
        'reason' => null,
        'ip' => '203.0.113.7',
        'user_agent' => 'PestTest/1.0',
        'locale' => 'de_DE',
        'store_id' => 1,
        'received_at_microtime' => microtime(true),
    ], $overrides));
}

describe('Revocation submission service', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore();
        $store->setConfig('revocation/general/enabled', '1');
        $store->setConfig('trans_email/ident_general/email', 'shop@example.com');
        $store->setConfig('trans_email/ident_general/name', 'Test Shop');
        // No MTA on CI runners: disable the transport so send() reports success without dispatching.
        $store->setConfig('system/smtp/enabled', '');
        $this->service = Mage::getModel('revocation/service');
        $this->createdRequests = [];
        $this->createdOrders = [];
        $this->createdCustomers = [];
    });

    afterEach(function () {
        foreach ($this->createdRequests as $request) {
            $request->delete();
        }
        foreach ($this->createdOrders as $order) {
            $order->delete();
        }
        foreach ($this->createdCustomers as $customer) {
            $customer->delete();
        }
    });

    it('writes a revocation request row when the public form is submitted', function () {
        $input = revocation_submit_input();
        $request = $this->service->submit($input);
        $this->createdRequests[] = $request;

        expect($request->getId())->toBeGreaterThan(0);

        $reloaded = Mage::getModel('revocation/request')->load($request->getId());
        expect((int) $reloaded->getId())->toBe((int) $request->getId());
        expect($reloaded->getCustomerName())->toBe('Max Mustermann');
        expect($reloaded->getEmail())->toBe($input->getEmail());
        expect($reloaded->getOrderReference())->toBe($input->getOrderReference());
        expect((int) $reloaded->getVerified())->toBe(0);
        expect($reloaded->getOrderId())->toBeNull();
        expect($reloaded->getIp())->toBe('203.0.113.7');
        expect($reloaded->getUserAgent())->toBe('PestTest/1.0');
        expect($reloaded->getLocale())->toBe('de_DE');
        expect((int) $reloaded->getStoreId())->toBe(1);
        expect($reloaded->getReceivedAt())->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
    });

    it('captures received_at in UTC from the submit moment', function () {
        $request = $this->service->submit(revocation_submit_input([
            'received_at_microtime' => 1781000000.123456,
        ]));
        $this->createdRequests[] = $request;

        expect($request->getReceivedAt())->toBe(gmdate('Y-m-d H:i:s', 1781000000));
    });

    it('sends both the receipt and merchant emails on submit', function () {
        $request = $this->service->submit(revocation_submit_input());
        $this->createdRequests[] = $request;

        expect($request->getData('receipt_email_sent'))->toBeTrue();
        expect($request->getData('merchant_email_sent'))->toBeTrue();
        expect($request->getSuppressedAt())->toBeNull();
    });

    it('matches the order via order reference + name + email but does not change order state', function () {
        $order = revocation_create_order();
        $this->createdOrders[] = $order;
        $stateBefore = $order->getState();
        $statusBefore = $order->getStatus();

        $request = $this->service->submit(revocation_submit_input([
            'email' => $order->getCustomerEmail(),
            'order_reference' => $order->getIncrementId(),
            'customer_name' => 'Max Mustermann',
        ]));
        $this->createdRequests[] = $request;

        expect((int) $request->getOrderId())->toBe((int) $order->getId());
        expect((int) $request->getVerified())->toBe(0);

        $reloadedOrder = Mage::getModel('sales/order')->load($order->getId());
        expect($reloadedOrder->getState())->toBe($stateBefore);
        expect($reloadedOrder->getStatus())->toBe($statusBefore);
    });

    it('does not auto-link order_id when the order number matches but email does not', function () {
        $order = revocation_create_order();
        $this->createdOrders[] = $order;

        $request = $this->service->submit(revocation_submit_input([
            'email' => 'someone-else-' . uniqid() . '@example.com',
            'order_reference' => $order->getIncrementId(),
            'customer_name' => 'Max Mustermann',
        ]));
        $this->createdRequests[] = $request;

        expect($request->getOrderId())->toBeNull();
        expect($request->getOrderReference())->toBe($order->getIncrementId());
    });

    it('does not auto-link order_id when the order number matches but the name does not', function () {
        $order = revocation_create_order();
        $this->createdOrders[] = $order;

        $request = $this->service->submit(revocation_submit_input([
            'email' => $order->getCustomerEmail(),
            'order_reference' => $order->getIncrementId(),
            'customer_name' => 'Completely Different',
        ]));
        $this->createdRequests[] = $request;

        expect($request->getOrderId())->toBeNull();
    });

    it('writes a row with order_id NULL when the order number does not exist', function () {
        $request = $this->service->submit(revocation_submit_input([
            'order_reference' => 'DOES-NOT-EXIST-' . uniqid(),
        ]));
        $this->createdRequests[] = $request;

        expect($request->getId())->toBeGreaterThan(0);
        expect($request->getOrderId())->toBeNull();
    });

    it('marks the request as verified and adds a status-history comment on the my-account path', function () {
        $customer = revocation_create_customer();
        $this->createdCustomers[] = $customer;
        $order = revocation_create_order(['customer_id' => (int) $customer->getId()]);
        $this->createdOrders[] = $order;
        $stateBefore = $order->getState();
        $statusBefore = $order->getStatus();

        $request = $this->service->submit(revocation_submit_input([
            'email' => $order->getCustomerEmail(),
            'order_reference' => $order->getIncrementId(),
            'customer_id' => (int) $customer->getId(),
            'session_order_id' => (int) $order->getId(),
        ]));
        $this->createdRequests[] = $request;

        expect((int) $request->getVerified())->toBe(1);
        expect((int) $request->getOrderId())->toBe((int) $order->getId());

        $reloadedOrder = Mage::getModel('sales/order')->load($order->getId());
        expect($reloadedOrder->getState())->toBe($stateBefore);
        expect($reloadedOrder->getStatus())->toBe($statusBefore);

        $comments = [];
        foreach ($reloadedOrder->getStatusHistoryCollection() as $history) {
            $comments[] = (string) $history->getComment();
        }
        expect(implode("\n", $comments))->toContain('revocation request #' . $request->getId());
    });

    it('does not trust the session order when the customer does not own it', function () {
        $owner = revocation_create_customer();
        $this->createdCustomers[] = $owner;
        $order = revocation_create_order(['customer_id' => (int) $owner->getId()]);
        $this->createdOrders[] = $order;

        $request = $this->service->submit(revocation_submit_input([
            'email' => 'attacker-' . uniqid() . '@example.com',
            'customer_name' => 'Someone Else',
            'customer_id' => (int) $owner->getId() + 1,
            'session_order_id' => (int) $order->getId(),
        ]));
        $this->createdRequests[] = $request;

        expect((int) $request->getVerified())->toBe(0);
        expect($request->getOrderId())->toBeNull();
    });

    it('writes the row and merchant email but suppresses the customer email when the per-recipient rate limit is exceeded', function () {
        $email = 'limited-' . uniqid() . '@example.com';

        $first = $this->service->submit(revocation_submit_input(['email' => $email]));
        $this->createdRequests[] = $first;
        expect($first->getSuppressedAt())->toBeNull();
        expect($first->getData('receipt_email_sent'))->toBeTrue();

        $second = $this->service->submit(revocation_submit_input(['email' => $email]));
        $this->createdRequests[] = $second;

        expect($second->getId())->toBeGreaterThan(0);
        expect($second->getSuppressedAt())->toBe($second->getReceivedAt());
        expect($second->getSuppressedReason())->toBe(Maho_Revocation_Model_Request::SUPPRESSED_REASON_RATE_LIMIT);
        expect($second->getData('receipt_email_sent'))->toBeFalse();
        expect($second->getData('merchant_email_sent'))->toBeTrue();
    });

    it('rejects submissions with missing required fields without writing a row', function () {
        $countBefore = Mage::getResourceModel('revocation/request_collection')->getSize();

        expect(fn() => $this->service->submit(revocation_submit_input(['customer_name' => '  '])))
            ->toThrow(Mage_Core_Exception::class);
        expect(fn() => $this->service->submit(revocation_submit_input(['email' => 'not-an-email'])))
            ->toThrow(Mage_Core_Exception::class);

        expect(Mage::getResourceModel('revocation/request_collection')->getSize())->toBe($countBefore);
    });
});
