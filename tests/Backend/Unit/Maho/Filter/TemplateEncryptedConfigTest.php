<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('getConfig() in a template variable', function () {
    beforeEach(function () {
        $store = new class extends \Maho\DataObject {
            public function getConfig(?string $path = null): string
            {
                return $path === null ? '' : 'value-of:' . $path;
            }
        };
        $this->filter = new \Maho\Filter\Template();
        $this->filter->setVariables(['store' => $store]);
    });

    it('does not return an encrypted value in any case of the method name', function (string $method) {
        expect($this->filter->filter("{{var store.{$method}('system/smtp/password')}}"))->toBe('');
    })->with(['getConfig', 'getconfig', 'GETCONFIG', 'GetConfig']);

    it('does not return the parent of an encrypted path', function (string $path) {
        expect($this->filter->filter("{{var store.getConfig('{$path}')}}"))->toBe('');
    })->with(['system/smtp', 'system', '/system/smtp/password/']);

    it('returns a value that is not encrypted', function (string $path) {
        expect($this->filter->filter("{{var store.getConfig('{$path}')}}"))->toBe('value-of:' . $path);
    })->with(['general/store_information/name', 'system/smtp/password_not']);
});

describe('getConfigData() in a template variable', function () {
    it('does not call getConfigData() in any case of the method name', function (string $method) {
        $payment = new class extends \Maho\DataObject {
            public function getConfigData(?string $field = null): string
            {
                return 'value-of:' . $field;
            }
        };
        $filter = new \Maho\Filter\Template();
        $filter->setVariables(['payment' => $payment]);

        expect($filter->filter("{{var payment.{$method}('trans_key')}}"))->toBe('');
    })->with(['getConfigData', 'getconfigdata', 'GETCONFIGDATA']);

    it('does not call getDataUsingMethod(), which calls a getter by name', function (string $method) {
        $object = new class extends \Maho\DataObject {
            public function getConfigData(?string $field = null): string
            {
                return 'value-of:' . $field;
            }

            public function getConfig(?string $path = null): string
            {
                return 'value-of:' . $path;
            }
        };
        $filter = new \Maho\Filter\Template();
        $filter->setVariables(['payment' => $object]);

        expect($filter->filter("{{var payment.{$method}('config_data', 'trans_key')}}"))->toBe('')
            ->and($filter->filter("{{var payment.{$method}('config', 'system/smtp/password')}}"))->toBe('');
    })->with(['getDataUsingMethod', 'getdatausingmethod', 'GETDATAUSINGMETHOD']);
});
