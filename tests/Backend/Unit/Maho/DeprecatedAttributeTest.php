<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

/**
 * Tripwire for #1696. A deprecated function, method, class constant or enum case takes the
 * #[\Deprecated] attribute, never a @deprecated docblock tag: only the attribute tells the caller
 * at runtime. PHP rejects the attribute on a class, an interface and a property, so those keep the tag.
 * Rector cannot enforce this: its DeprecatedAnnotationToDeprecatedAttributeRector is deprecated.
 */

uses(MahoBackendTestCase::class);

/**
 * Get the lines of the @deprecated docblock tags in $file that sit on a function, a method,
 * a class constant or an enum case.
 *
 * @return list<int>
 */
function docblockDeprecationLines(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $skip = [T_WHITESPACE, T_COMMENT, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY];
    $lines = [];

    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_DOC_COMMENT || !str_contains($token[1], '@deprecated')) {
            continue;
        }

        $j = $i + 1;
        while (isset($tokens[$j])) {
            $next = $tokens[$j];
            if (is_array($next) && $next[0] === T_ATTRIBUTE) {
                for ($depth = 1, $j++; isset($tokens[$j]) && $depth > 0; $j++) {
                    $depth += match ($tokens[$j]) {
                        '[' => 1,
                        ']' => -1,
                        default => 0,
                    };
                }
                continue;
            }
            if (is_array($next) && in_array($next[0], $skip, true)) {
                $j++;
                continue;
            }
            break;
        }

        if (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_FUNCTION, T_CONST, T_CASE], true)) {
            $lines[] = $token[2];
        }
    }

    return $lines;
}

it('deprecates functions, methods and class constants with the attribute', function () {
    $base = Mage::getBaseDir();
    $offenders = [];

    foreach (['app/code/core', 'lib'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$base/$dir", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (docblockDeprecationLines($file->getPathname()) as $line) {
                $offenders[] = substr($file->getPathname(), strlen($base) + 1) . ":$line";
            }
        }
    }

    expect($offenders)->toBe([], "Replace the @deprecated tag with #[\\Deprecated(message: '...', since: '...')] at:\n" . implode("\n", $offenders));
});

it('finds a tag on a method, a constant and an enum case, and not on a class or a property', function () {
    $file = (string) tempnam(sys_get_temp_dir(), 'deprecated');
    file_put_contents($file, <<<'PHP'
        <?php
        /** @deprecated */
        class A
        {
            /** @deprecated */
            public const X = 1;

            /** @deprecated */
            public $property;

            /** @deprecated */
            #[\Override]
            #[SomeAttribute(['key' => 'value'])]
            final public static function method() {}
        }
        enum E
        {
            /** @deprecated */
            case Case;
        }
        PHP);

    try {
        expect(docblockDeprecationLines($file))->toBe([5, 11, 18]);
    } finally {
        unlink($file);
    }
});
