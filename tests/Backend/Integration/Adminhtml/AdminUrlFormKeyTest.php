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
