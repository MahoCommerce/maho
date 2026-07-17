<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('Revocation button widget', function () {
    it('renders the revocation link when enabled', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '1');

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/widget_button')
            ->toHtml();

        expect($html)->toContain('/revocation');
        expect($html)->toContain('Revoke contract');
    });

    it('uses the configured button label by default', function () {
        $store = Mage::app()->getStore();
        $store->setConfig('revocation/general/enabled', '1');
        $store->setConfig('revocation/general/button_label', 'Vertrag widerrufen');

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/widget_button')
            ->toHtml();

        expect($html)->toContain('Vertrag widerrufen');
    });

    it('honors a custom label set on the widget instance', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '1');

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/widget_button')
            ->setData('label', 'Withdraw from contract')
            ->toHtml();

        expect($html)->toContain('Withdraw from contract');
    });

    it('renders nothing when the feature is disabled for the current store', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '0');

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/widget_button')
            ->toHtml();

        expect($html)->toBe('');
    });
});

describe('Revocation success page', function () {
    it('shows the request reference and the formatted receipt timestamp prominently', function () {
        Mage::register('revocation_success', [
            'request_id' => 12345,
            'received_at' => '2026-06-09 10:30:45',
        ]);

        $block = Mage::app()->getLayout()
            ->createBlock('revocation/success')
            ->setTemplate('revocation/success.phtml');
        $html = $block->toHtml();

        expect($html)->toContain('#12345');
        // The page shows the receipt time in the store timezone (e.g. 10:30:45 UTC
        // renders as 11:30:45 in Europe/London), so assert against the block's value.
        expect($block->getReceivedAtStore())->toContain('2026-06-09');
        expect($html)->toContain($block->getReceivedAtStore());
        expect($html)->toContain('receipt only');

        Mage::unregister('revocation_success');
    });
});

describe('Revocation form prefill', function () {
    it('pre-fills the form from the my-account entry point order', function () {
        $order = Mage::getModel('sales/order');
        $order->setId(424242);
        $order->setIncrementId('100000077');
        $order->setCustomerFirstname('Erika');
        $order->setCustomerLastname('Musterfrau');
        $order->setCustomerEmail('erika@example.com');
        Mage::register('revocation_prefill_order', $order);

        $block = Mage::app()->getLayout()->createBlock('revocation/form');
        $data = $block->getFormData();

        expect($block->isPrefilled())->toBeTrue();
        expect($data->getCustomerName())->toBe('Erika Musterfrau');
        expect($data->getEmail())->toBe('erika@example.com');
        expect($data->getOrderReference())->toBe('100000077');
        expect((int) $data->getSessionOrderId())->toBe(424242);

        Mage::unregister('revocation_prefill_order');
    });
});

describe('Revocation my-account order link', function () {
    it('renders the link for a recent order within the cooling-off window', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '1');

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('100000088');
        $order->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        Mage::register('current_order', $order);

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/order_link')
            ->setTemplate('revocation/order_link.phtml')
            ->toHtml();

        expect($html)->toContain('link-revocation');

        Mage::unregister('current_order');
    });

    it('hides the link when the cooling-off window has passed', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '1');

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('100000099');
        $order->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('-30 days'));
        Mage::register('current_order', $order);

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/order_link')
            ->setTemplate('revocation/order_link.phtml')
            ->toHtml();

        expect($html)->toBe('');

        Mage::unregister('current_order');
    });

    it('hides the link when the feature is disabled', function () {
        Mage::app()->getStore()->setConfig('revocation/general/enabled', '0');

        $order = Mage::getModel('sales/order');
        $order->setIncrementId('100000100');
        $order->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        Mage::register('current_order', $order);

        $html = Mage::app()->getLayout()
            ->createBlock('revocation/order_link')
            ->setTemplate('revocation/order_link.phtml')
            ->toHtml();

        expect($html)->toBe('');

        Mage::unregister('current_order');
    });
});
