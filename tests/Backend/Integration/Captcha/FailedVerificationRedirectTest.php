<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

uses(Tests\MahoBackendTestCase::class);

class CaptchaRedirectProbe extends Maho_Captcha_Model_Observer
{
    public function fail(Mage_Core_Controller_Front_Action $controller): void
    {
        $this->failedVerification($controller);
    }
}

function captchaRedirectResponse(string $returnUrl): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        Request::create('/checkout/onepage/saveBilling', 'POST', ['referer_url' => $returnUrl]),
    );
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new class ($request, $response) extends Mage_Core_Controller_Front_Action {};
    (new CaptchaRedirectProbe())->fail($controller);
    return $response;
}

function captchaRedirectLocation(Mage_Core_Controller_Response_Http $response): string
{
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Location') === 0) {
            return $header['value'];
        }
    }
    return '';
}

beforeEach(function () {
    Mage::getSingleton('core/session')->getMessages(true);
});

it('keeps an internal CAPTCHA failure destination', function () {
    $destination = Mage::app()->getStore()->getBaseUrl() . 'checkout/cart/';

    $response = captchaRedirectResponse($destination);

    expect($response->isRedirect())->toBeTrue()
        ->and(captchaRedirectLocation($response))->toBe($destination);
});

it('replaces an external CAPTCHA failure destination with the storefront base URL', function () {
    $response = captchaRedirectResponse('https://example.invalid/checkout');

    expect($response->isRedirect())->toBeTrue()
        ->and(captchaRedirectLocation($response))->toBe(Mage::getBaseUrl());
});
