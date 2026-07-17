<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/**
 * Guards the SOAP/XML-RPC/JSON-RPC protocol gate.
 *
 * The legacy Mage_Api_*Controller classes must NOT own the /api/{protocol}
 * routes: if they do, their bare route shadows Maho_ApiPlatform_IndexController
 * (which gates each protocol behind apiplatform/protocols/*), silently leaving
 * SOAP/XML-RPC/JSON-RPC open even when the admin config says they are disabled.
 */
describe('Legacy protocol routing gate', function (): void {

    $matcherFile = dirname(__DIR__, 5) . '/vendor/composer/maho_url_matcher.php';

    beforeEach(function () use ($matcherFile): void {
        if (!is_file($matcherFile)) {
            $this->markTestSkipped('Compiled route matcher not found (run composer dump-autoload)');
        }
        $this->matcher = file_get_contents($matcherFile);
    });

    test('each legacy protocol path is owned by the gated IndexController', function (): void {
        foreach (['/api/soap', '/api/v2_soap', '/api/xmlrpc', '/api/jsonrpc'] as $path) {
            expect($this->matcher)->toContain("'{$path}' =>");

            // The first controller listed for this exact path must be the gate.
            $offset = strpos($this->matcher, "'{$path}' =>");
            $segment = substr($this->matcher, $offset, 600);
            expect($segment)->toContain('Maho_ApiPlatform_IndexController');
        }
    });

    test('the legacy Mage_Api protocol controllers own no compiled routes', function (): void {
        foreach ([
            'Mage_Api_SoapController',
            'Mage_Api_V2_SoapController',
            'Mage_Api_XmlrpcController',
            'Mage_Api_JsonrpcController',
        ] as $controller) {
            expect($this->matcher)->not->toContain($controller);
        }
    });
});
