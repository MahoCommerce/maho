<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

// FrankenPHP defines headers_send(). The CLI does not, so this stub records each informational response.
if (!function_exists('headers_send')) {
    function headers_send(int $status = 200): int
    {
        $GLOBALS['sentInformationalResponses'][] = $status;
        return $status;
    }
}

it('sends one 103 Early Hints response for all the files of the head', function (): void {
    $GLOBALS['sentInformationalResponses'] = [];
    $store = Mage::app()->getStore();
    $store->setConfig('dev/js/minify_enabled', '1');
    $store->setConfig('dev/js/defer_mode', (string) Mage_Core_Model_Source_Js_Defer::MODE_DISABLED);

    $html = Mage::app()->getLayout()->createBlock('page/html_head')
        ->addJs('varien/js.js')
        ->addJs('maho-dialog.js')
        ->addJs('validation.js')
        ->getCssJsHtml();

    preg_match_all('#/media/mahominify/([^"]+)#', $html, $matches);
    foreach ($matches[1] as $file) {
        @unlink(Mage::getBaseDir() . '/public/media/mahominify/' . $file);
    }

    expect($matches[1])->toHaveCount(3)
        ->and($GLOBALS['sentInformationalResponses'])->toBe([103]);
});
