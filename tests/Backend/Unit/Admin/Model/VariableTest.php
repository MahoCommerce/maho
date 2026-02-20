<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Admin_Model_Variable Validation', function () {
    beforeEach(function () {
        $this->variable = Mage::getModel('admin/variable');
    });

    it('validates successfully with valid variable data', function () {
        $this->variable->setVariableName('test_variable');
        $this->variable->setIsAllowed('1');
        $result = $this->variable->validate();
        expect($result)->toBeTrue();
    });

    describe('variable name validation', function () {
        it('fails validation when variable name is empty', function () {
            $this->variable->setVariableName('');
            $result = $this->variable->validate();
            expect($result)->toBeArray();
            expect($result[0])->toContain('Variable Name is required field.');
        });

        it('fails validation when variable name is blank', function () {
            $this->variable->setVariableName('   ');
            $this->variable->setIsAllowed('1');
            $result = $this->variable->validate();
            expect($result)->toBeArray();
            expect($result[0])->toContain('Variable Name is incorrect.');
        });

        it('fails validation for incorrect variable name format', function () {
            $this->variable->setVariableName('invalid@name');
            $result = $this->variable->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Variable Name is incorrect.');
        });

        it('passes validation for valid variable name formats', function () {
            $validNames = [
                'test_variable',
                'variable123',
                'test-variable',
                'simple',
                'UPPERCASE_VAR',
                'mixed_Case123',
                'path/to/variable',
                'nested/path/to/var',
            ];

            foreach ($validNames as $validName) {
                $this->variable->setVariableName($validName);
                $this->variable->setIsAllowed('1');
                $result = $this->variable->validate();
                expect($result)->toBeTrue("Variable name '{$validName}' should be valid");
            }
        });

        it('fails validation for invalid characters in variable name', function () {
            $invalidNames = [
                'invalid@name',
                'variable#name',
                'test!variable',
                'variable*name',
                'test%variable',
                'variable name', // space is not allowed
                'variable+name',
                'variable=name',
            ];

            foreach ($invalidNames as $invalidName) {
                $this->variable->setVariableName($invalidName);
                $result = $this->variable->validate();
                expect($result)->toBeArray("Variable name '{$invalidName}' should be invalid");
                expect($result)->toContain('Variable Name is incorrect.');
            }
        });

        it('allows empty variable names according to regex pattern', function () {
            // The regex '/^[-_a-zA-Z0-9\/]*$/' allows empty strings
            // but isValidNotBlank check should catch empty strings first
            $this->variable->setVariableName('');
            $result = $this->variable->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Variable Name is required field.');
        });

        it('allows forward slashes for nested variable names', function () {
            $nestedNames = [
                'parent/child',
                'level1/level2/level3',
                'category/subcategory/item',
            ];

            foreach ($nestedNames as $nestedName) {
                $this->variable->setVariableName($nestedName);
                $this->variable->setIsAllowed('1');
                $result = $this->variable->validate();
                expect($result)->toBeTrue("Nested variable name '{$nestedName}' should be valid");
            }
        });

        it('allows underscores and hyphens in variable names', function () {
            $validNames = [
                'test_variable',
                'test-variable',
                '_leading_underscore',
                'trailing_underscore_',
                '-leading-hyphen',
                'trailing-hyphen-',
                'mixed_hyphen-underscore_name',
            ];

            foreach ($validNames as $validName) {
                $this->variable->setVariableName($validName);
                $this->variable->setIsAllowed('1');
                $result = $this->variable->validate();
                expect($result)->toBeTrue("Variable name '{$validName}' should be valid");
            }
        });
    });

    it('returns multiple errors when multiple validation rules fail', function () {
        $this->variable->setVariableName('');
        $result = $this->variable->validate();
        expect($result)->toBeArray();
        expect(count($result))->toBeGreaterThan(0);
    });
});
