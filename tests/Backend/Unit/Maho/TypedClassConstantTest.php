<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassConst;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Tests\MahoBackendTestCase;

/**
 * Tripwire for the native type of a class constant. Each class, interface, trait and enum constant
 * in core declares its type: `public const string XML_PATH_ENABLED = '...'`. A subclass that
 * redeclares a typed constant must declare a compatible type, or PHP stops with a fatal error.
 * Rector cannot enforce this: AddTypeToConstRector skips the constants that a subclass can redeclare.
 */

uses(MahoBackendTestCase::class);

/**
 * Get the names and lines of the class constants in $code that have no type.
 *
 * @return list<string>
 */
function untypedClassConstants(string $code): array
{
    $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
    $untyped = [];

    foreach ((new NodeFinder())->findInstanceOf($ast, ClassConst::class) as $stmt) {
        if ($stmt->type !== null) {
            continue;
        }
        foreach ($stmt->consts as $const) {
            $untyped[] = $const->name->toString() . ':' . $const->getStartLine();
        }
    }

    return $untyped;
}

it('declares a type on each class constant', function () {
    $base = Mage::getBaseDir();
    $offenders = [];
    $files = [new SplFileInfo("$base/app/Mage.php")];

    foreach (['app/code/core', 'lib'] as $dir) {
        $files = [...$files, ...new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$base/$dir", FilesystemIterator::SKIP_DOTS))];
    }

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (untypedClassConstants((string) file_get_contents($file->getPathname())) as $constant) {
            $offenders[] = substr($file->getPathname(), strlen($base) + 1) . ":$constant";
        }
    }

    expect($offenders)->toBe([], "Declare the type of these class constants, for example `public const string NAME = 'value'`:\n" . implode("\n", $offenders));
});

it('finds an untyped constant in a class, an interface, a trait and an enum, and not a global constant', function () {
    $code = <<<'PHP'
        <?php
        const GLOBAL_CONSTANT = 1;
        class A
        {
            public const string TYPED = 'a';
            public const UNTYPED = 'a', SECOND = 'b';
        }
        interface I
        {
            const UNTYPED = 1;
        }
        trait T
        {
            final protected const UNTYPED = [];
        }
        enum E
        {
            private const UNTYPED = 1.5;
            public const int TYPED = 1;
        }
        PHP;

    expect(untypedClassConstants($code))->toBe(['UNTYPED:6', 'SECOND:6', 'UNTYPED:10', 'UNTYPED:14', 'UNTYPED:18']);
});
