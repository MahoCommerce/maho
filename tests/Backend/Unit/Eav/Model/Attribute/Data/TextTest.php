<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Eav_Model_Attribute_Data_Text::validateValue()', function () {
    beforeEach(function (): void {
        $attribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'middlename');
        $this->dataModel = Mage::getModel('eav/attribute_data_text')
            ->setAttribute($attribute)
            ->setEntity(Mage::getModel('customer/customer'));
    });

    it('accepts a string', function (): void {
        expect($this->dataModel->validateValue('Q'))->toBeTrue();
    });

    it('accepts an empty value of an optional attribute', function (): void {
        expect($this->dataModel->validateValue(null))->toBeTrue()
            ->and($this->dataModel->validateValue(''))->toBeTrue();
    });

    it('refuses an array', function (): void {
        $result = $this->dataModel->validateValue(['Q', 'R']);

        expect($result)->toBeArray()
            ->and($result[0])->toContain('contains invalid data');
    });
});
