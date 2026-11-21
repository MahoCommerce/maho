<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Payment_Helper_Data::getInfoBlockHtmlForStore()', function () {
    it('renders the method code when the payment method is unknown', function () {
        $info = Mage::getModel('payment/info')->setMethod('no_such_method');

        expect(Mage::helper('payment')->getInfoBlockHtmlForStore($info, 1))->toBe('no_such_method');
    });

    it('gives the payment method the store of the email', function () {
        $info = Mage::getModel('payment/info')->setMethod('checkmo');

        Mage::helper('payment')->getInfoBlockHtmlForStore($info, 1);

        expect((int) $info->getMethodInstance()->getStore())->toBe(1);
    });
});
