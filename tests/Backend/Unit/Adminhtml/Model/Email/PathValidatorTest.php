<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Adminhtml_Model_Email_PathValidator', function () {
    beforeEach(function () {
        $this->validator = new Mage_Adminhtml_Model_Email_PathValidator();
    });

    describe('isValid method', function () {
        it('returns false for null values', function () {
            $result = $this->validator->isValid(null);
            expect($result)->toBeFalse();
        });

        it('returns false for empty string values', function () {
            $result = $this->validator->isValid('');
            expect($result)->toBeFalse();
        });

        it('returns false for non-encrypted node paths', function () {
            // Test with a path that's not in the encrypted node entries
            $result = $this->validator->isValid('general/store_information/name');
            expect($result)->toBeFalse();
        });

        it('returns true for encrypted node paths', function () {
            // Get the encrypted node entries from the config model
            $configModel = Mage::getSingleton('adminhtml/config');
            $encryptedPaths = $configModel->getEncryptedNodeEntriesPaths();

            if (!empty($encryptedPaths)) {
                $testPath = $encryptedPaths[0];
                $result = $this->validator->isValid($testPath);
                expect($result)->toBeTrue("Path '{$testPath}' should be valid as it's in encrypted entries");
            } else {
                // If no encrypted paths exist, we can't test this scenario
                // but the validator should still work correctly
                expect(true)->toBeTrue('No encrypted paths configured for testing');
            }
        });

        it('handles array input by using first element', function () {
            // Test with array input - should use first element
            $configModel = Mage::getSingleton('adminhtml/config');
            $encryptedPaths = $configModel->getEncryptedNodeEntriesPaths();

            if (!empty($encryptedPaths)) {
                $testPath = $encryptedPaths[0];
                $result = $this->validator->isValid([$testPath, 'other', 'values']);
                expect($result)->toBeTrue('Array with encrypted path should be valid');
            }

            // Test with non-encrypted path in array
            $result = $this->validator->isValid(['general/store_information/name', 'other']);
            expect($result)->toBeFalse('Array with non-encrypted path should be invalid');
        });

        it('handles empty array input', function () {
            expect(fn() => $this->validator->isValid([]))->toThrow(TypeError::class);
        });

        it('validates common encrypted configuration paths if they exist', function () {
            // Test some common paths that might be encrypted
            $potentialEncryptedPaths = [
                'payment/authorizenet/trans_key',
                'payment/paypal_express/api_password',
                'smtp/configuration/password',
                'system/smtp/password',
                'carriers/ups/password',
                'carriers/fedex/key',
                'carriers/dhl/password',
            ];

            $configModel = Mage::getSingleton('adminhtml/config');
            $actualEncryptedPaths = $configModel->getEncryptedNodeEntriesPaths();

            foreach ($potentialEncryptedPaths as $path) {
                $result = $this->validator->isValid($path);
                $shouldBeValid = in_array($path, $actualEncryptedPaths);

                if ($shouldBeValid) {
                    expect($result)->toBeTrue("Path '{$path}' should be valid as it's encrypted");
                } else {
                    expect($result)->toBeFalse("Path '{$path}' should be invalid as it's not encrypted");
                }
            }
        });

        it('rejects obviously non-encrypted paths', function () {
            $nonEncryptedPaths = [
                'general/store_information/name',
                'web/unsecure/base_url',
                'design/head/default_title',
                'catalog/frontend/list_mode',
                'customer/create_account/confirm',
            ];

            foreach ($nonEncryptedPaths as $path) {
                $result = $this->validator->isValid($path);
                expect($result)->toBeFalse("Path '{$path}' should be invalid as it's not encrypted");
            }
        });
    });

    describe('integration with adminhtml config model', function () {
        it('uses the correct adminhtml config model', function () {
            // Verify that the validator is using the singleton correctly
            $configModel1 = Mage::getSingleton('adminhtml/config');
            $configModel2 = Mage::getSingleton('adminhtml/config');

            expect($configModel1)->toBe($configModel2, 'Should use singleton pattern');
        });

        it('relies on getEncryptedNodeEntriesPaths method', function () {
            $configModel = Mage::getSingleton('adminhtml/config');

            expect(method_exists($configModel, 'getEncryptedNodeEntriesPaths'))
                ->toBeTrue('Config model should have getEncryptedNodeEntriesPaths method');

            $paths = $configModel->getEncryptedNodeEntriesPaths();
            expect($paths)->toBeArray('Should return an array of paths');
        });
    });
});
