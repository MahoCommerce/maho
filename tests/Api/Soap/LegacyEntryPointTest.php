<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * @param array<string, string> $flags
 */
function setLegacyProtocols(array $flags): void
{
    $config = Mage::getModel('core/config');
    foreach ($flags as $protocol => $value) {
        $config->saveConfig('apiplatform/protocols/' . $protocol, $value, 'default', 0);
    }
    Mage::app()->getCache()->cleanType('config');
}

function callXmlrpcThroughApiPhp(): int
{
    $body = '<?xml version="1.0"?><methodCall><methodName>resources</methodName><params/></methodCall>';
    return ApiV2Helper::postRaw('/api.php?type=xmlrpc', $body, null, ['Content-Type' => 'text/xml'])['status'];
}

describe('api.php protocol switches', function (): void {

    it('answers 404 for a protocol that is off, when legacy REST is on', function (): void {
        setLegacyProtocols(['legacy_rest' => '1', 'xmlrpc' => '0']);
        try {
            expect(callXmlrpcThroughApiPhp())->toBe(404);
        } finally {
            setLegacyProtocols(['legacy_rest' => '1', 'xmlrpc' => '1']);
        }
    });

    it('serves a protocol that is on, when legacy REST is off', function (): void {
        setLegacyProtocols(['legacy_rest' => '0', 'xmlrpc' => '1']);
        try {
            expect(callXmlrpcThroughApiPhp())->not->toBe(404);
        } finally {
            setLegacyProtocols(['legacy_rest' => '1', 'xmlrpc' => '1']);
        }
    });
});
