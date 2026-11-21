<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class PaymentHelperDataTestInfoBlock extends Mage_Payment_Block_Info
{
    #[\Override]
    protected function _toHtml(): string
    {
        return (string) Mage::app()->getStore()->getId();
    }
}

class PaymentHelperDataTestMethod extends Mage_Payment_Model_Method_Checkmo
{
    protected $_infoBlockType = PaymentHelperDataTestInfoBlock::class;
}

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

    it('renders in the store of the email and puts back the store of the caller', function () {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $info = Mage::getModel('payment/info')->setMethod('checkmo');
        $info->setMethodInstance(new PaymentHelperDataTestMethod()->setInfoInstance($info));

        $html = Mage::helper('payment')->getInfoBlockHtmlForStore($info, 1);

        expect($html)->toBe('1')
            ->and((int) Mage::app()->getStore()->getId())->toBe(Mage_Core_Model_App::ADMIN_STORE_ID);
    });
});
