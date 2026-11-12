<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the DHL site ID and password out of the debug data of a tracking request', function (): void {
    $carrier = new class extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public array $debugData = [];

        #[\Override]
        public function getConfigData(mixed $field): ?string
        {
            return match ($field) {
                'id' => '1',
                'password' => 'TEST_PASSWORD_MARKER',
                'gateway_url' => 'unsupported-scheme://localhost',
                default => null,
            };
        }

        #[\Override]
        protected function _debug(mixed $debugData): void
        {
            $this->debugData = $debugData;
        }
    };

    $carrier->getTracking('AWB1001');

    $request = $carrier->debugData['request'];
    expect($request)->toContain('>AWB1001</AWBNumber>');
    expect($request)->toContain('<SiteID>***</SiteID><Password>***</Password>');
    expect($request)->not->toContain('TEST_PASSWORD_MARKER');
});

it('keeps the DHL site ID and password out of the debug data of a rate request', function (): void {
    $carrier = new class extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public array $debugData = [];

        #[\Override]
        public function getConfigData(mixed $field): ?string
        {
            return match ($field) {
                'id' => '1',
                'password' => 'TEST_PASSWORD_MARKER',
                'unit_of_measure' => 'LB',
                default => null,
            };
        }

        #[\Override]
        protected function _debug(mixed $debugData): void
        {
            $this->debugData = $debugData;
        }

        #[\Override]
        protected function _getQuotesFromServer(mixed $request): string
        {
            return '<r/>';
        }

        public function requestQuotes(): void
        {
            $this->_rawRequest = new \Maho\DataObject(['orig_country_id' => 'US', 'dest_country_id' => 'US']);
            $this->_request = new \Maho\DataObject(['all_items' => []]);
            $this->_getQuotes();
        }
    };

    $carrier->requestQuotes();

    $request = $carrier->debugData['try-0']['request'];
    expect($request)->toContain('<SiteID>***</SiteID><Password>***</Password>');
    expect($request)->not->toContain('TEST_PASSWORD_MARKER');
});
