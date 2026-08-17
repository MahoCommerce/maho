<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function onestepCheckoutHtml(): string
{
    return Mage::app()->getLayout()
        ->createBlock('checkout/onepage')
        ->setTemplate('checkout/onestep.phtml')
        ->toHtml();
}

describe('One step checkout account links', function () {
    it('shows the login and register links when customer accounts are enabled', function () {
        Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');

        $html = onestepCheckoutHtml();

        expect($html)->toContain('customer/account/login');
        expect($html)->toContain('customer/account/create');
    });

    it('hides the login and register links when customer accounts are disabled', function () {
        Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '0');

        $html = onestepCheckoutHtml();

        expect($html)->not->toContain('customer/account/login');
        expect($html)->not->toContain('customer/account/create');
    });
});
