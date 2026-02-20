<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Admin_Model_Block Validation', function () {
    beforeEach(function () {
        $this->block = Mage::getModel('admin/block');
    });

    it('validates successfully with valid block data', function () {
        $this->block->setBlockName('catalog/product_list');
        $this->block->setIsAllowed('1'); // Set required field
        $result = $this->block->validate();
        expect($result)->toBeTrue();
    });

    describe('block name validation', function () {
        it('fails validation when block name is empty', function () {
            $this->block->setBlockName('');
            $result = $this->block->validate();
            expect($result)->toBeArray();
            expect($result[0])->toContain('Block Name is required field.');
        });

        it('fails validation when block name is blank', function () {
            $this->block->setBlockName('   ');
            $result = $this->block->validate();
            expect($result)->toBeArray();
            expect($result[0])->toContain('Block Name is incorrect.');
        });

        it('fails validation for disallowed block names', function () {
            // Test with a disallowed block name - first let's get the disallowed names
            $disallowedNames = Mage::helper('admin/block')->getDisallowedBlockNames();

            if (!empty($disallowedNames)) {
                $this->block->setBlockName($disallowedNames[0]);
                $result = $this->block->validate();
                expect($result)->toBeArray();
                expect($result)->toContain('Block Name is disallowed.');
            }
        });

        it('fails validation for incorrect block name format', function () {
            $this->block->setBlockName('invalid-format!');
            $result = $this->block->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Block Name is incorrect.');
        });

        it('fails validation for block name without slash', function () {
            $this->block->setBlockName('invalidformat');
            $result = $this->block->validate();
            expect($result)->toBeArray();
            expect($result)->toContain('Block Name is incorrect.');
        });

        it('passes validation for valid block name formats', function () {
            $validNames = [
                'catalog/product_list',
                'customer/account_dashboard',
                'cms/block',
                'admin/user',
                'core/template',
                'sales/order/view',
            ];

            foreach ($validNames as $validName) {
                $this->block->setBlockName($validName);
                $this->block->setIsAllowed('1'); // Set required field
                $result = $this->block->validate();
                expect($result)->toBeTrue("Block name '{$validName}' should be valid");
            }
        });

        it('fails validation for invalid characters in block name', function () {
            $invalidNames = [
                'catalog/product@list',
                'customer/account#dashboard',
                'cms/block!',
                'admin/user*',
                'core/template%',
            ];

            foreach ($invalidNames as $invalidName) {
                $this->block->setBlockName($invalidName);
                $result = $this->block->validate();
                expect($result)->toBeArray("Block name '{$invalidName}' should be invalid");
                expect($result)->toContain('Block Name is incorrect.');
            }
        });
    });

    it('returns multiple errors when multiple validation rules fail', function () {
        $this->block->setBlockName('');
        $result = $this->block->validate();
        expect($result)->toBeArray();
        expect(count($result))->toBeGreaterThan(0);
    });
});
