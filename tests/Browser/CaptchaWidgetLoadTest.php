<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser', 'captcha');

/**
 * The widget script loads only when a form gets focus or a submit. Until then, the
 * altcha-widget element has no getState() and no verify(): the state of a browser that fires
 * the load event before the methods exist. These tests send that event by hand.
 */

beforeEach(function () {
    setCaptchaWidgetLoadConfig(true);
});

afterEach(function () {
    setCaptchaWidgetLoadConfig(false);
});

function setCaptchaWidgetLoadConfig(bool $enabled): void
{
    $config = Mage::getModel('core/config');
    if ($enabled) {
        $config->saveConfig(Maho_Captcha_Helper_Data::XML_PATH_ENABLED, '1');
    } else {
        $config->deleteConfig(Maho_Captcha_Helper_Data::XML_PATH_ENABLED);
    }
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
}

/** Run $script in the page, store its result in window.__result, and wait for it. */
function captchaWidgetLoadResult(object $page, string $script): mixed
{
    $page->script("window.__result = undefined; (async () => { window.__result = await ({$script}); })()");
    waitForScript($page, 'window.__result !== undefined', 10);

    return $page->script('window.__result');
}

it('answers not verified when the widget methods never appear', function () {
    $page = visit(MahoServer::baseUrl() . '/contacts/');
    waitForPageLoad($page, '#contactForm');

    $result = captchaWidgetLoadResult($page, <<<'JS'
    (async () => {
        const widget = document.querySelector('altcha-widget');
        const first = MahoCaptcha.waitForVerification();
        widget.dispatchEvent(new Event('load'));
        const firstResult = await first;
        const secondResult = await MahoCaptcha.waitForVerification();
        return [firstResult, secondResult, MahoCaptcha.altchaState];
    })()
    JS);

    expect($result)->toBe([false, false, 'error']);
});

it('starts the verification when the widget methods appear after the load event', function () {
    $page = visit(MahoServer::baseUrl() . '/contacts/');
    waitForPageLoad($page, '#contactForm');

    $result = captchaWidgetLoadResult($page, <<<'JS'
    (async () => {
        const widget = document.querySelector('altcha-widget');
        const pending = MahoCaptcha.waitForVerification();
        widget.dispatchEvent(new Event('load'));
        setTimeout(() => {
            widget.getState = () => 'unverified';
            widget.verify = () => widget.dispatchEvent(
                new CustomEvent('statechange', { detail: { state: 'verified', payload: 'stub' } }),
            );
        }, 100);
        const verified = await pending;
        return [verified, document.querySelector('#contactForm input[name=maho_captcha]')?.value ?? null];
    })()
    JS);

    expect($result)->toBe([true, 'stub']);
});
