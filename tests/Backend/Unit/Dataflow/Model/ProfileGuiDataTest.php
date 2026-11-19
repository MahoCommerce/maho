<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The values of the profile wizard reach the actions XML as data. They cannot close their element or
 * their CDATA section, and they cannot add an action.
 */

/**
 * @return array<string, mixed> a valid export profile of the wizard
 */
function profileGuiDataExport(): array
{
    return [
        'entity_type' => 'product',
        'direction' => 'export',
        'data_transfer' => 'file',
        'store_id' => 0,
        'gui_data' => [
            'file' => ['type' => 'file', 'path' => 'var/export', 'filename' => 'export.csv'],
            'parse' => ['type' => 'csv', 'delimiter' => ',', 'enclose' => '"', 'fieldnames' => 'true'],
            'map' => ['product' => ['db' => [], 'file' => []], 'only_specified' => ''],
            'product' => ['filter' => []],
        ],
    ];
}

it('escapes the values of the profile wizard in the actions XML', function (array $profileData, int $expectedActions, array $expectedVars, array $expectedMaps): void {
    $profile = Mage::getModel('dataflow/profile')->setData($profileData);

    $profile->_parseGuiData();

    $xml = new SimpleXMLElement('<profile>' . $profile->getActionsXml() . '</profile>');
    expect($xml->action)->toHaveCount($expectedActions);
    foreach ($expectedVars as $name => $value) {
        $vars = $xml->xpath("//var[@name='$name']");
        expect($vars)->not->toBeEmpty()
            ->and((string) $vars[0])->toBe($value);
    }

    $maps = [];
    foreach ($xml->xpath('//map') ?: [] as $map) {
        $maps[(string) $map['name']] = (string) $map;
    }
    expect($maps)->toBe($expectedMaps);
})->with(function (): Generator {
    $closeCdata = 'x]]></var></action><action type="core/convert_adapter_http_curl" method="load"><var name="uri"><![CDATA[http://evil';
    $closeElement = 'a</var></action><action type="core/convert_adapter_http_curl" method="load"><var name="uri">http://evil';

    yield 'export to a local file' => [
        [
            'entity_type' => 'product',
            'direction' => 'export',
            'data_transfer' => 'file',
            'store_id' => '1"><x',
            'gui_data' => [
                'file' => ['type' => 'file', 'path' => $closeElement, 'filename' => $closeCdata],
                'parse' => ['type' => 'csv', 'delimiter' => ',', 'enclose' => '"', 'fieldnames' => $closeElement],
                'map' => [
                    'product' => ['db' => ['sku'], 'file' => [$closeCdata]],
                    'only_specified' => $closeElement,
                ],
                'product' => ['filter' => ['name' => $closeCdata, 'price' => ['from' => $closeCdata]]],
                'export' => ['add_url_field' => $closeCdata],
            ],
        ],
        // Product load, product unparse, column map, CSV unparse, file save
        5,
        [
            'store' => '1',
            'path' => $closeElement,
            'filename' => $closeCdata,
            'fieldnames' => $closeElement,
            '_only_specified' => $closeElement,
            'filter/name' => $closeCdata,
            'filter/price/from' => $closeCdata,
            'url_field' => $closeCdata,
        ],
        ['sku' => $closeCdata],
    ];

    yield 'import from ftp' => [
        [
            'entity_type' => 'customer',
            'direction' => 'import',
            'data_transfer' => 'file',
            'store_id' => 1,
            'gui_data' => [
                'file' => [
                    'type' => 'ftp',
                    'path' => '/in',
                    'filename' => 'customers.csv',
                    'host' => $closeCdata,
                    'user' => $closeCdata,
                    'password' => $closeCdata,
                ],
                'parse' => ['type' => 'csv', 'delimiter' => $closeCdata, 'enclose' => $closeCdata, 'fieldnames' => 'true'],
                'map' => [
                    'customer' => ['db' => [$closeCdata], 'file' => ['email" type="x']],
                    'only_specified' => '',
                ],
                'import' => ['number_of_records' => $closeElement, 'decimal_separator' => $closeCdata],
            ],
        ],
        // File load, CSV parse
        2,
        [
            'host' => 'x]]></var></action><action type="core/convert_adapter_http_curl" method="load"><var name="uri"><![CDATA[http',
            'user' => $closeCdata,
            'password' => $closeCdata,
            'delimiter' => $closeCdata,
            'enclose' => $closeCdata,
            'number_of_records' => $closeElement,
            'decimal_separator' => $closeCdata,
        ],
        ['email" type="x' => $closeCdata],
    ];
});

it('refuses a value that the profile wizard does not offer', function (Closure $change): void {
    $data = profileGuiDataExport();
    $change($data);
    $profile = Mage::getModel('dataflow/profile')->setData($data);

    expect(fn() => $profile->_parseGuiData())->toThrow(Mage_Core_Exception::class);
})->with([
    'advanced profile' => [function (array &$data): void {
        $data['entity_type'] = null;
    }],
    'unknown entity type' => [function (array &$data): void {
        $data['entity_type'] = 'order';
    }],
    'unknown file type' => [function (array &$data): void {
        $data['gui_data']['file']['type'] = 'file</var><var name="x">';
    }],
    'unknown file format' => [function (array &$data): void {
        $data['gui_data']['parse']['type'] = 'csv2';
    }],
]);

it('makes the actions XML of a valid profile of the wizard', function (): void {
    $profile = Mage::getModel('dataflow/profile')->setData(profileGuiDataExport());

    $profile->_parseGuiData();

    expect(new SimpleXMLElement('<profile>' . $profile->getActionsXml() . '</profile>')->action)->toHaveCount(5);
});
