<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class SoapFaultProbe extends Mage_Api_Model_Server_Adapter_Soap
{
    public function xml(int $code, string $message): string
    {
        return $this->_getFaultXml($code, $message);
    }
}

it('escapes text in the fallback SOAP fault envelope', function () {
    $xml = (new SoapFaultProbe())->xml(500, 'A & B < C > D "quoted"');

    $document = simplexml_load_string($xml);
    $document->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
    $fault = $document->xpath('//soap:Fault')[0];

    expect($document)->not->toBeFalse()
        ->and((string) $fault->faultcode)->toBe('500')
        ->and((string) $fault->faultstring)->toBe('A & B < C > D "quoted"');
});
