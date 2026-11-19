<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A custom variable code is part of the {{customVar code=...}} directive that the Insert Variable
 * dialog writes into content. GHSA-qrpm-g4h5-qrgr: a code must not hold quotes, braces or markup.
 */
describe('Mage_Core_Model_Variable code', function () {
    beforeEach(function (): void {
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->table = Mage::getSingleton('core/resource')->getTableName('core/variable');
        $this->codes = [];
    });

    afterEach(function (): void {
        foreach ($this->codes as $code) {
            $this->adapter->delete($this->table, ['code = ?' => $code]);
        }
    });

    it('accepts a code that starts with a letter and holds letters, digits, underscores, hyphens and slashes', function (string $code): void {
        expect(Mage_Core_Model_Variable::isValidCode($code))->toBeTrue();
    })->with([
        'letters' => 'footer',
        'all characters' => 'Shop_info-2/phone',
    ]);

    it('refuses a code that is not a valid variable code', function (mixed $code): void {
        expect(Mage_Core_Model_Variable::isValidCode($code))->toBeFalse();
    })->with([
        'empty' => '',
        'starts with a digit' => '1abc',
        'quote' => 'a"b',
        'closing braces' => 'a}}<img src=x onerror=alert(1)>',
        'space' => 'a b',
        'line feed at the end' => "abc\n",
        'not a string' => [['abc']],
    ]);

    it('returns an error from validate() for a code that is not valid', function (): void {
        $variable = Mage::getModel('core/variable')
            ->setCode('x}}<script>alert(1)</script>')
            ->setName('Bad code');

        expect($variable->validate())->toBeString();
    });

    it('refuses to save a new variable with a code that is not valid', function (): void {
        $code = 'bad"code' . uniqid();
        $this->codes[] = $code;

        expect(fn() => Mage::getModel('core/variable')->setCode($code)->setName('Bad code')->save())
            ->toThrow(Mage_Core_Exception_Input::class);

        expect(Mage::getModel('core/variable')->loadByCode($code)->getId())->toBeNull();
    });

    it('saves a new variable with a valid code', function (): void {
        $code = 'test_code_' . uniqid();
        $this->codes[] = $code;

        $variable = Mage::getModel('core/variable')->setCode($code)->setName('Good code')->save();

        expect($variable->getId())->not->toBeNull();
    });

    it('keeps an existing code that is not valid when the code does not change', function (): void {
        $code = 'legacy code ' . uniqid();
        $this->codes[] = $code;
        $this->adapter->insert($this->table, ['code' => $code, 'name' => 'Legacy']);

        $variableId = Mage::getModel('core/variable')->loadByCode($code)->getId();
        Mage::getModel('core/variable')->load($variableId)->setName('Legacy renamed')->save();

        expect(Mage::getModel('core/variable')->loadByCode($code)->getName())->toBe('Legacy renamed');
    });

    it('keeps an existing code that is not valid when a variable that loadByCode() loaded is saved', function (): void {
        $code = 'legacy code ' . uniqid();
        $this->codes[] = $code;
        $this->adapter->insert($this->table, ['code' => $code, 'name' => 'Legacy']);

        Mage::getModel('core/variable')->loadByCode($code)->setName('Legacy renamed')->save();

        expect(Mage::getModel('core/variable')->loadByCode($code)->getName())->toBe('Legacy renamed');
    });

    it('refuses to change the code of an existing variable to a code that is not valid', function (): void {
        $code = 'test_code_' . uniqid();
        $badCode = 'bad code ' . uniqid();
        $this->codes[] = $code;
        $this->codes[] = $badCode;
        $variable = Mage::getModel('core/variable')->setCode($code)->setName('Good code')->save();

        expect(fn() => Mage::getModel('core/variable')->load($variable->getId())->setCode($badCode)->save())
            ->toThrow(Mage_Core_Exception_Input::class);
    });
});
