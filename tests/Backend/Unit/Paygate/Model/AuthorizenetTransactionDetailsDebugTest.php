<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the merchant credentials out of the debug data of a transaction details request', function () {
    $method = new class extends Mage_Paygate_Model_Authorizenet {
        public array $debugData = [];

        #[\Override]
        public function getConfigData($field, $storeId = null)
        {
            return match ($field) {
                'login' => 'TEST_LOGIN_MARKER',
                'trans_key' => 'TEST_KEY_MARKER',
                'cgi_url_td' => 'unsupported-scheme://localhost',
                default => null,
            };
        }

        #[\Override]
        protected function _debug($debugData)
        {
            $this->debugData = $debugData;
            return $this;
        }

        public function requestTransactionDetails(string $transactionId): void
        {
            $this->_getTransactionDetails($transactionId);
        }
    };

    expect(fn() => $method->requestTransactionDetails('TEST_TRANSACTION_ID'))
        ->toThrow(Mage_Core_Exception::class);

    $request = $method->debugData['request'];
    expect($request)->toContain('TEST_TRANSACTION_ID');
    expect($request)->toContain('<name>***</name><transactionKey>***</transactionKey>');
    expect($request)->not->toContain('TEST_LOGIN_MARKER');
    expect($request)->not->toContain('TEST_KEY_MARKER');
});
