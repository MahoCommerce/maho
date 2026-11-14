<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('CSV escaping', function () {
    beforeEach(function () {
        Mage::app()->getStore()->setConfig(Mage_ImportExport_Model_Export_Adapter_Csv::CONFIG_ESCAPING_FLAG, '1');
        $this->helper = Mage::helper('core');
    });

    it('puts a space before a value that a spreadsheet reads as a formula', function (string $value) {
        expect($this->helper->getEscapedCSVData([$value]))->toBe([' ' . $value]);
    })->with(['=1+1', '+1', '-1', '@SUM(A1)']);

    it('keeps any other value', function () {
        expect($this->helper->getEscapedCSVData(['text', 'a@b.com', '']))->toBe(['text', 'a@b.com', '']);
    });

    it('reads back the escaped values', function () {
        $values = ['=1+1', '+1', '-1', '@SUM(A1)', 'text'];

        expect($this->helper->unEscapeCSVData($this->helper->getEscapedCSVData($values)))->toBe($values);
    });
});
