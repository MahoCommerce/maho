<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Product Option File Extension Security Validation', function () {
    beforeEach(function () {
        $this->option = Mage::getModel('catalog/product_option');
        $this->option->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE);

        // Use reflection to access protected method
        $this->reflection = new ReflectionClass($this->option);
        $this->validateMethod = $this->reflection->getMethod('validateFileExtensions');
        $this->validateMethod->setAccessible(true);
    });

    describe('validateFileExtensions method', function () {
        it('allows safe file extensions without throwing exceptions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'jpg,png,pdf,txt'))
                ->not()->toThrow(Mage_Core_Exception::class);
        });

        it('throws exception for PHP extensions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'jpg,php,png'))
                ->toThrow(Mage_Core_Exception::class, 'The following file extensions are not allowed for security reasons: php');
        });

        it('throws exception for multiple forbidden extensions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'exe,php,bat'))
                ->toThrow(Mage_Core_Exception::class);
        });

        it('throws exception for executable file extensions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'exe,bat,cmd'))
                ->toThrow(Mage_Core_Exception::class);
        });

        it('throws exception for script file extensions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'phtml,js,vbs'))
                ->toThrow(Mage_Core_Exception::class);
        });

        it('handles case-insensitive forbidden extensions', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'JPG,PHP,PNG'))
                ->toThrow(Mage_Core_Exception::class, 'The following file extensions are not allowed for security reasons: php');
        });

        it('throws exception with all forbidden extensions listed', function () {
            expect(fn() => $this->validateMethod->invoke($this->option, 'php,exe,js,phtml'))
                ->toThrow(Mage_Core_Exception::class);
        });

        it('returns self when validation passes', function () {
            $result = $this->validateMethod->invoke($this->option, 'jpg,png,pdf');
            expect($result)->toBe($this->option);
        });
    });

    describe('_beforeSave validation integration', function () {
        it('validates file extensions during beforeSave for file type options', function () {
            $this->option->setFileExtension('php,exe');

            expect(fn() => $this->option->save())
                ->toThrow(Mage_Core_Exception::class);
        });

        it('skips validation for non-file type options', function () {
            $this->option->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD);
            $this->option->setFileExtension('php'); // This should be ignored

            // Should not throw exception because it's not a file type option
            expect(fn() => $this->option->save())->not()->toThrow(Mage_Core_Exception::class);
        });

        it('allows safe extensions during beforeSave', function () {
            $this->option->setFileExtension('jpg,png,pdf');

            expect(fn() => $this->option->save())->not()->toThrow(Mage_Core_Exception::class);
        });
    });

    describe('saveOptions batch validation', function () {
        beforeEach(function () {
            $this->product = Mage::getModel('catalog/product');
            $this->product->setId(1);
            $this->product->setStoreId(0);
            $this->option->setProduct($this->product);
        });

        it('validates extensions during batch save operations', function () {
            $optionData = [
                'type' => Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE,
                'title' => 'Test File Option',
                'file_extension' => 'php,exe',
                'is_require' => 0,
            ];

            $this->option->setOptions([$optionData]);

            expect(fn() => $this->option->saveOptions())
                ->toThrow(Mage_Core_Exception::class);
        });

        it('allows safe extensions during batch save operations', function () {
            $optionData = [
                'type' => Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE,
                'title' => 'Test File Option',
                'file_extension' => 'jpg,png,pdf',
                'is_require' => 0,
            ];

            $this->option->setOptions([$optionData]);

            expect(fn() => $this->option->saveOptions())->not()->toThrow(Mage_Core_Exception::class);
        });
    });
})->group('security');
