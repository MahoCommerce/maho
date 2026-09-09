<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The OAuth authorize login page is reachable before the admin logs in and prints the
 * consumer name, so the name must be escaped in both the full and the simple template.
 */
function renderOauthLoginTemplate(string $template): string
{
    $helper = Mage::helper('oauth');

    $consumer = Mage::getModel('oauth/consumer')->setData([
        'name' => 'Evil <script>alert("consumer")</script> app',
        'key' => $helper->generateConsumerKey(),
        'secret' => $helper->generateConsumerSecret(),
    ]);
    $consumer->save();

    $token = Mage::getModel('oauth/token')
        ->createRequestToken($consumer->getId(), Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED);

    $design = Mage::getDesign();
    $previousArea = $design->getArea();
    $design->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);

    try {
        $layout = Mage::app()->getLayout();
        $layout->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);

        return $layout->createBlock('oauth/adminhtml_oauth_authorize')
            ->setTemplate($template)
            ->setToken($token->getToken())
            ->toHtml();
    } finally {
        $design->setArea($previousArea);
        $token->delete();
        $consumer->delete();
    }
}

it('escapes the consumer name on the login page', function (string $template) {
    $html = renderOauthLoginTemplate($template);

    expect($html)->not->toContain('<script>alert')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;consumer&quot;)&lt;/script&gt;');
})->with([
    'full' => 'oauth/authorize/form/login.phtml',
    'simple' => 'oauth/authorize/form/login-simple.phtml',
]);
