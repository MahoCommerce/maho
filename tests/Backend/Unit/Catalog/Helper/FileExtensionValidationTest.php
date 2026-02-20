<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Catalog Helper File Extension Security Validation', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('catalog');
    });

    describe('validateFileExtensionsAgainstForbiddenList', function () {
        it('returns empty arrays for empty input', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('');

            expect($result)->toBe([
                'allowed' => [],
                'forbidden' => [],
                'original' => '',
            ]);
        });

        it('allows safe file extensions', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('jpg,png,pdf,txt');

            expect($result['allowed'])->toBe(['jpg', 'png', 'pdf', 'txt']);
            expect($result['forbidden'])->toBe([]);
            expect($result['original'])->toBe('jpg,png,pdf,txt');
        });

        it('blocks forbidden extensions like PHP files', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('jpg,php,png');

            expect($result['allowed'])->toBe(['jpg', 'png']);
            expect($result['forbidden'])->toBe(['php']);
            expect($result['original'])->toBe('jpg,php,png');
        });

        it('blocks multiple forbidden extensions', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('exe,bat,php,phtml');

            expect($result['allowed'])->toBe([]);
            expect($result['forbidden'])->toBe(['exe', 'bat', 'php', 'phtml']);
        });

        it('handles case-insensitive extension validation', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('JPG,PHP,PNG,EXE');

            expect($result['allowed'])->toBe(['jpg', 'png']);
            expect($result['forbidden'])->toBe(['php', 'exe']);
        });

        it('parses extensions with various separators', function () {
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList('jpg; png, pdf php');

            expect($result['allowed'])->toBe(['jpg', 'png', 'pdf']);
            expect($result['forbidden'])->toBe(['php']);
        });

        it('blocks all dangerous script extensions', function () {
            $dangerousExtensions = 'php,phtml,php3,php4,php5,js,vbs,pl,py,rb';
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList($dangerousExtensions);

            expect($result['allowed'])->toBe([]);
            expect($result['forbidden'])->toContain('php', 'phtml', 'js', 'vbs');
        });

        it('blocks executable file extensions', function () {
            $executableExtensions = 'exe,bat,cmd,com,scr';
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList($executableExtensions);

            expect($result['allowed'])->toBe([]);
            expect($result['forbidden'])->toContain('exe', 'bat', 'cmd');
        });

        it('allows mixed valid and blocks dangerous extensions', function () {
            $mixedExtensions = 'jpg,php,png,exe,pdf,js,txt';
            $result = $this->helper->validateFileExtensionsAgainstForbiddenList($mixedExtensions);

            expect($result['allowed'])->toBe(['jpg', 'png', 'pdf', 'txt']);
            expect($result['forbidden'])->toBe(['php', 'exe', 'js']);
        });
    });
});
