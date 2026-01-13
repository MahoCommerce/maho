<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Dataflow Parser Path Traversal Security', function () {
    beforeEach(function () {
        $this->importDir = Mage::app()->getConfig()->getTempVarDir() . '/import';

        // Ensure import directory exists
        if (!is_dir($this->importDir)) {
            mkdir($this->importDir, 0755, true);
        }

        // Create a valid test file
        $this->validFile = 'test_import_' . uniqid() . '.csv';
        file_put_contents($this->importDir . '/' . $this->validFile, "col1,col2\nval1,val2");
    });

    afterEach(function () {
        // Clean up test file
        $testFilePath = $this->importDir . '/' . $this->validFile;
        if (file_exists($testFilePath)) {
            unlink($testFilePath);
        }
    });

    /**
     * Helper function that replicates the validation logic from the parsers
     * using \Maho\Io::validatePath()
     */
    function validateImportPath(string $param, string $importDir): string
    {
        $file = \Maho\Io::validatePath($importDir . '/' . urldecode($param), $importDir);
        if ($file === false) {
            throw new Mage_Core_Exception('Invalid file path.');
        }
        return $file;
    }

    describe('path traversal attack prevention', function () {
        it('blocks basic path traversal with ../etc/passwd', function () {
            expect(fn() => validateImportPath('../etc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks path traversal with ../../etc/passwd', function () {
            expect(fn() => validateImportPath('../../etc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks bypass attempt with ..././etc/passwd', function () {
            expect(fn() => validateImportPath('..././etc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks bypass attempt with ....//....//etc/passwd', function () {
            expect(fn() => validateImportPath('....//....//etc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks URL-encoded path traversal with %2e%2e%2f', function () {
            expect(fn() => validateImportPath('%2e%2e%2fetc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks double URL-encoded path traversal', function () {
            expect(fn() => validateImportPath('%252e%252e%252fetc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks absolute path attempts', function () {
            expect(fn() => validateImportPath('/etc/passwd', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks phar:// stream wrapper', function () {
            expect(fn() => validateImportPath('phar://malicious.phar', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });

        it('blocks http:// stream wrapper', function () {
            expect(fn() => validateImportPath('http://evil.com/file', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });
    });

    describe('valid file handling', function () {
        it('allows valid file in import directory', function () {
            $result = validateImportPath($this->validFile, $this->importDir);
            expect($result)->toBe(realpath($this->importDir . '/' . $this->validFile));
        });

        it('allows valid file in subdirectory', function () {
            // Create subdirectory and file
            $subdir = $this->importDir . '/subdir';
            if (!is_dir($subdir)) {
                mkdir($subdir, 0755, true);
            }
            $subFile = 'subfile_' . uniqid() . '.csv';
            file_put_contents($subdir . '/' . $subFile, 'test');

            try {
                $result = validateImportPath('subdir/' . $subFile, $this->importDir);
                expect($result)->toBe(realpath($subdir . '/' . $subFile));
            } finally {
                unlink($subdir . '/' . $subFile);
                rmdir($subdir);
            }
        });
    });

    describe('non-existent file handling', function () {
        it('rejects non-existent files', function () {
            expect(fn() => validateImportPath('nonexistent_file.csv', $this->importDir))
                ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
        });
    });

    describe('symlink attack prevention', function () {
        it('blocks symlinks pointing outside import directory', function () {
            // Skip on Windows where symlinks work differently
            if (PHP_OS_FAMILY === 'Windows') {
                $this->markTestSkipped('Symlink tests not reliable on Windows');
            }

            $symlinkName = 'malicious_link_' . uniqid();
            $symlinkPath = $this->importDir . '/' . $symlinkName;

            // Create symlink pointing to /etc (outside import dir)
            if (@symlink('/etc', $symlinkPath)) {
                try {
                    // Attempt to access passwd through the symlink
                    expect(fn() => validateImportPath($symlinkName . '/passwd', $this->importDir))
                        ->toThrow(Mage_Core_Exception::class, 'Invalid file path.');
                } finally {
                    unlink($symlinkPath);
                }
            } else {
                $this->markTestSkipped('Unable to create symlink (may require elevated permissions)');
            }
        });
    });
})->group('security');
