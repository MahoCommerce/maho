<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the DHL site ID and password out of the debug data of a tracking request', function () {
    $carrier = new class extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public array $debugData = [];

        #[\Override]
        public function getConfigData($field)
        {
            return match ($field) {
                'id' => '1',
                'password' => 'TEST_PASSWORD_MARKER',
                'gateway_url' => 'unsupported-scheme://localhost',
                default => null,
            };
        }

        #[\Override]
        protected function _debug($debugData)
        {
            $this->debugData = $debugData;
        }
    };

    $carrier->getTracking('AWB1001');

    $request = $carrier->debugData['request'];
    expect($request)->toContain('<AWBNumber>AWB1001</AWBNumber>');
    expect($request)->toContain('<SiteID>***</SiteID><Password>***</Password>');
    expect($request)->not->toContain('TEST_PASSWORD_MARKER');
});
