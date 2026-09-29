<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use Maho\DirectoryData\Paths;

uses(Tests\MahoBackendTestCase::class);

it('checks the postcode format of the country', function (string $countryId, string $postcode, bool $valid) {
    expect((new Mage_Directory_Model_AddressFormat())->isValidPostcode($countryId, $postcode))->toBe($valid);
})->with([
    'IT valid' => ['IT', '00144', true],
    'IT too short' => ['IT', '0014', false],
    'US five digits' => ['US', '95014', true],
    'US ZIP+4' => ['US', '22162-1010', true],
    'US too short' => ['US', '9501', false],
    'GB with space' => ['GB', 'EC1Y 8SY', true],
    'GB lower case without space' => ['GB', 'ec1y8sy', true],
    'GB digits only' => ['GB', '12345', false],
    'DE valid' => ['DE', '53225', true],
    'DE letters' => ['DE', '5322A', false],
    'surrounding spaces' => ['IT', ' 00144 ', true],
]);

it('accepts every postcode when it has no rule to apply', function (?string $countryId, ?string $postcode) {
    expect((new Mage_Directory_Model_AddressFormat())->isValidPostcode($countryId, $postcode))->toBeTrue();
})->with([
    'unknown country' => ['XX', 'anything'],
    'path in the country id' => ['../x', 'anything'],
    'lower case country id' => ['it', 'anything'],
    'no country' => [null, '0014'],
    'empty postcode' => ['IT', ''],
    'no postcode' => ['IT', null],
    'country without a postcode rule' => ['AE', 'anything'],
]);

it('returns the first example and the pattern of a country', function () {
    $addressFormat = new Mage_Directory_Model_AddressFormat();

    expect($addressFormat->getPostcodeExample('IT'))->toBe('00144')
        ->and($addressFormat->getPostcodePattern('IT'))->toBe('\d{5}')
        ->and($addressFormat->getPostcodeExample('AE'))->toBeNull()
        ->and($addressFormat->getPostcodePattern('AE'))->toBeNull();
});

it('lists the postcode formats only for the countries that have a rule', function () {
    $formats = (new Mage_Directory_Model_AddressFormat())->getPostcodeFormats(['IT', 'AE', 'XX']);

    expect($formats)->toBe(['IT' => ['pattern' => '\d{5}', 'example' => '00144']]);
});

it('accepts every postcode example of the package with the pattern of its country', function () {
    $addressFormat = new Mage_Directory_Model_AddressFormat();
    $checked = 0;
    foreach (glob(Paths::formatsDir() . '/*.json') as $file) {
        $countryId = basename($file, '.json');
        $example = $addressFormat->getPostcodeExample($countryId);
        if ($example === null) {
            continue;
        }
        expect($addressFormat->isValidPostcode($countryId, $example))->toBeTrue("{$countryId} rejects {$example}");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(100);
});
