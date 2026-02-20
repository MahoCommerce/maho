<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoFrontendTestCase::class);

describe('Frontend File Extension Display Filtering', function () {
    beforeEach(function () {
        $this->block = new Mage_Catalog_Block_Product_View_Options_Type_File();
        $this->option = Mage::getModel('catalog/product_option');
        $this->option->setType(Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE);
        $this->block->setOption($this->option);
    });

    describe('getSanitizedFileExtension', function () {
        it('displays only safe file extensions to customers', function () {
            $this->option->setFileExtension('jpg,png,pdf,txt');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png, pdf, txt');
        });

        it('filters out PHP extensions from display', function () {
            $this->option->setFileExtension('jpg,php,png');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png');
            expect($result)->not()->toContain('php');
        });

        it('filters out all executable extensions from display', function () {
            $this->option->setFileExtension('jpg,exe,png,bat,cmd');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png');
            expect($result)->not()->toContain('exe', 'bat', 'cmd');
        });

        it('filters out script extensions from display', function () {
            $this->option->setFileExtension('pdf,js,txt,vbs,phtml');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('pdf, txt');
            expect($result)->not()->toContain('js', 'vbs', 'phtml');
        });

        it('returns empty string when no safe extensions remain', function () {
            $this->option->setFileExtension('php,exe,bat,js,phtml');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('');
        });

        it('returns empty string for empty input', function () {
            $this->option->setFileExtension('');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('');
        });

        it('handles case-insensitive filtering for display', function () {
            $this->option->setFileExtension('JPG,PHP,PNG,EXE');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png');
            expect($result)->not()->toContain('php', 'exe', 'PHP', 'EXE');
        });

        it('properly formats comma-separated safe extensions for display', function () {
            $this->option->setFileExtension('jpg,png,gif,pdf,doc,txt');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png, gif, pdf, doc, txt');
            expect($result)->toContain(', '); // Proper comma-space formatting
        });

        it('filters mixed safe and dangerous extensions correctly', function () {
            $this->option->setFileExtension('jpg,php,png,exe,pdf,js,txt,bat');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png, pdf, txt');
            expect($result)->not()->toContain('php', 'exe', 'js', 'bat');
        });

        it('handles extensions with various separators and formatting', function () {
            $this->option->setFileExtension('jpg; png, pdf php exe');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg, png, pdf');
            expect($result)->not()->toContain('php', 'exe');
        });
    });

    describe('security defense in depth', function () {
        it('never displays dangerous extensions even if somehow saved to database', function () {
            // Simulate a scenario where dangerous extensions were somehow saved
            $dangerousExtensions = 'php,phtml,php3,php4,php5,php7,php8,phar,exe,bat,cmd,com,scr,vbs,js,jar,py,pl,rb,sh,asp,aspx,jsp,cgi,htaccess';
            $this->option->setFileExtension($dangerousExtensions);

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe(''); // All should be filtered out
        });

        it('prevents security information disclosure through extension display', function () {
            // Test that no forbidden extensions leak through to customer display
            $mixedExtensions = 'jpg,php,png,exe,pdf,js,txt,phtml,bat,doc';
            $this->option->setFileExtension($mixedExtensions);

            $result = $this->block->getSanitizedFileExtension();
            $displayedExtensions = explode(', ', $result);

            $forbiddenExtensions = ['php', 'exe', 'js', 'phtml', 'bat'];
            foreach ($forbiddenExtensions as $forbidden) {
                expect($displayedExtensions)->not()->toContain($forbidden);
            }

            $safeExtensions = ['jpg', 'png', 'pdf', 'txt', 'doc'];
            foreach ($safeExtensions as $safe) {
                expect($displayedExtensions)->toContain($safe);
            }
        });
    });

    describe('user experience', function () {
        it('provides clean comma-separated format for customer display', function () {
            $this->option->setFileExtension('jpg,png,pdf');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toMatch('/^[a-z0-9]+(, [a-z0-9]+)*$/'); // Clean format pattern
        });

        it('handles single safe extension correctly', function () {
            $this->option->setFileExtension('jpg');

            $result = $this->block->getSanitizedFileExtension();

            expect($result)->toBe('jpg');
        });

        it('gracefully handles edge cases without errors', function () {
            $edgeCases = ['', '   ', 'jpg,,,png', 'JPG;PNG,PDF'];

            foreach ($edgeCases as $edgeCase) {
                $this->option->setFileExtension($edgeCase);

                expect(fn() => $this->block->getSanitizedFileExtension())
                    ->not()->toThrow(Exception::class);
            }
        });
    });
})->group('frontend', 'security');
