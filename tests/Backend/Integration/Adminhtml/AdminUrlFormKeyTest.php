<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The form key is the session-wide CSRF root the per-action secret key is derived from, so it
 * must never ride in an admin url that doesn't need it. Only getUrlSecure() may emit it, and
 * only when secret keys are off, which is the sole config where _setForcedFormKeyActions()
 * arms itself. With secret keys on nothing reads it, so no url should contain it.
 */

function adminUrlSetSecretKey(bool $enabled): void
{
    Mage::app()->getStore()->setConfig(
        Mage_Adminhtml_Helper_Data::XML_PATH_ADMINHTML_SECURITY_USE_FORM_KEY,
        $enabled ? 1 : 0,
    );
}

function adminUrlBlock(): Mage_Adminhtml_Block_Template
{
    /** @var Mage_Adminhtml_Block_Template $block */
    $block = Mage::app()->getLayout()->createBlock('adminhtml/template');
    return $block;
}

afterEach(function () {
    Mage::app()->getStore()->resetConfig();
});

it('keeps the form key out of admin urls when secret keys are on', function () {
    adminUrlSetSecretKey(true);
    $block = adminUrlBlock();
    $plain = $block->getUrl('adminhtml/sales_order/view', ['order_id' => 1]);

    expect($plain)->not->toContain('form_key')
        ->and($plain)->toContain('/key/')
        ->and($block->getUrlSecure('adminhtml/sales_order/hold', ['order_id' => 1]))->not->toContain('form_key');
});

it('emits the form key only from getUrlSecure when secret keys are off', function () {
    adminUrlSetSecretKey(false);
    $block = adminUrlBlock();
    $plain = $block->getUrl('adminhtml/sales_order/view', ['order_id' => 1]);

    expect($plain)->not->toContain('form_key')
        ->and($plain)->not->toContain('/key/')
        ->and($block->getUrlSecure('adminhtml/sales_order/hold', ['order_id' => 1]))
        ->toContain('form_key/' . Mage::getSingleton('core/session')->getFormKey());
});

it('builds grid action params with the same form key rule as urls', function () {
    adminUrlSetSecretKey(true);
    expect(adminUrlBlock()->getUrlSecureParams())->toBe([]);

    adminUrlSetSecretKey(false);
    expect(adminUrlBlock()->getUrlSecureParams(['id' => 7]))
        ->toBe(['id' => 7, 'form_key' => Mage::getSingleton('core/session')->getFormKey()]);
});

/**
 * Every delete button on a form container is a plain GET navigation, and the delete action is on
 * the forced-form-key list of most admin controllers, so the url has to carry the key when secret
 * keys are off and must not leak it when they're on.
 */
it('scopes the form key on the shared form container delete url', function () {
    /** @var Mage_Adminhtml_Block_Widget_Form_Container $block */
    $block = Mage::app()->getLayout()->createBlock('adminhtml/widget_form_container');

    adminUrlSetSecretKey(true);
    expect($block->getDeleteUrl())->not->toContain('form_key');

    adminUrlSetSecretKey(false);
    expect($block->getDeleteUrl())
        ->toContain('form_key/' . Mage::getSingleton('core/session')->getFormKey());
});

/**
 * These buttons build their own url instead of going through the container, so each one has to
 * opt into getUrlSecure() by hand. setLocation() used to paper over a miss by appending the key
 * to every navigation; without it a miss is a dead button.
 */
it('carries the form key on buttons that build their own forced-action url', function (string $blockAlias, string $method) {
    adminUrlSetSecretKey(false);
    $formKey = Mage::getSingleton('core/session')->getFormKey();

    $block = Mage::app()->getLayout()->createBlock($blockAlias);

    expect($block->{$method}())->toContain('form_key/' . $formKey);

    adminUrlSetSecretKey(true);
    expect(Mage::app()->getLayout()->createBlock($blockAlias)->{$method}())->not->toContain('form_key');
})->with([
    'queue retry' => ['queue/adminhtml_message_view', 'getRetryUrl'],
    'queue discard' => ['queue/adminhtml_message_view', 'getDiscardUrl'],
    'revocation resend' => ['revocation/adminhtml_request_view', 'getResendUrl'],
]);

/**
 * Instantiated directly because _prepareLayout() needs a registered category; only the url
 * matters here. The tree's delete button is the one navigation category.js no longer patches
 * the key onto, so the block has to supply it.
 */
it('carries the form key on the category tree delete url', function () {
    $block = new Mage_Adminhtml_Block_Catalog_Category_Edit_Form();

    adminUrlSetSecretKey(false);
    expect($block->getDeleteUrl())
        ->toContain('form_key/' . Mage::getSingleton('core/session')->getFormKey());

    adminUrlSetSecretKey(true);
    expect($block->getDeleteUrl())->not->toContain('form_key');
});
