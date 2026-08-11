<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Admin GET protection is always on: every admin url carries the per-action secret key, and
 * the session-wide form key it is derived from never rides in a url (POST carries it in a
 * hidden input instead). The only exception is the explicit _nosecret opt-out used by the
 * login flow and RSS feeds, where no session exists to validate against.
 */

function adminUrlBlock(): Mage_Adminhtml_Block_Template
{
    /** @var Mage_Adminhtml_Block_Template $block */
    $block = Mage::app()->getLayout()->createBlock('adminhtml/template');
    return $block;
}

afterEach(function () {
    Mage::unregister('current_creditmemo');
});

it('always puts the secret key in admin urls and keeps the form key out', function () {
    $url = adminUrlBlock()->getUrl('adminhtml/sales_order/view', ['order_id' => 1]);

    expect($url)->toContain('/key/')
        ->and($url)->not->toContain('form_key');
});

it('matches the secret key in the url to the one the action validates', function () {
    $url = adminUrlBlock()->getUrl('adminhtml/sales_order/view', ['order_id' => 1]);

    preg_match('#/key/([^/]+)/#', $url, $m);
    expect($m[1] ?? null)->toBe(Mage::getSingleton('adminhtml/url')->getSecretKey('sales_order', 'view'));
});

it('drops the secret key only on urls that opt out via _nosecret', function () {
    expect(adminUrlBlock()->getUrl('adminhtml/index/forgotpassword', ['_nosecret' => true]))
        ->not->toContain('/key/');
});

/**
 * Buttons that build their own GET navigation used to need a hand-wired form key opt-in;
 * now they all go through getUrl() and pick the secret key up automatically.
 */
it('carries the secret key on buttons that build their own action url', function (string $blockAlias, string $method) {
    $url = Mage::app()->getLayout()->createBlock($blockAlias)->{$method}();

    expect($url)->toContain('/key/')
        ->and($url)->not->toContain('form_key');
})->with([
    'queue retry' => ['queue/adminhtml_message_view', 'getRetryUrl'],
    'queue discard' => ['queue/adminhtml_message_view', 'getDiscardUrl'],
    'revocation resend' => ['revocation/adminhtml_request_view', 'getResendUrl'],
]);

it('carries the secret key on the shared form container delete url', function () {
    /** @var Mage_Adminhtml_Block_Widget_Form_Container $block */
    $block = Mage::app()->getLayout()->createBlock('adminhtml/widget_form_container');

    expect($block->getDeleteUrl())->toContain('/key/')
        ->and($block->getDeleteUrl())->not->toContain('form_key');
});

it('carries the secret key on the credit memo cancel and void urls', function (string $method) {
    Mage::register('current_creditmemo', Mage::getModel('sales/order_creditmemo')
        ->setId(1)
        ->setState(Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED));

    $block = Mage::app()->getLayout()->createBlock('adminhtml/sales_order_creditmemo_view');

    expect($block->{$method}())->toContain('/key/')
        ->and($block->{$method}())->not->toContain('form_key');
})->with(['getCancelUrl', 'getVoidUrl']);

it('carries the secret key on the category tree delete url', function () {
    $block = new Mage_Adminhtml_Block_Catalog_Category_Edit_Form();

    expect($block->getDeleteUrl())->toContain('/key/')
        ->and($block->getDeleteUrl())->not->toContain('form_key');
});
