<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Pins the access chain: only the resource model touches the rate table, and only the helper
 * and the currency model's deprecated delegates reach the resource. The scan covers app/code
 * only; deprecated-method calls are enforced separately by phpstan-deprecation-rules.
 */

/**
 * Files allowed to name the rate table, and why.
 *
 * @return array<string, string>
 */
function rateTableWriters(): array
{
    return [
        'core/Mage/Directory/Model/Resource/Currency.php' =>
            'The resource model is the answerer: it is the only code that reads or writes the table.',
        'core/Mage/Directory/data/directory_setup/data-install-1.6.0.0.php' =>
            'Install data, a snapshot of the table at that version.',
        'core/Mage/Directory/sql/schema.php' =>
            'The schema that declares the table.',
    ];
}

/**
 * Files allowed to reach the rate resource model directly, and why.
 *
 * @return array<string, string>
 */
function rateResourceCallers(): array
{
    return [
        'core/Mage/Directory/Helper/Data.php' =>
            'The public answerer: it reads the resource directly, so asking it never runs the deprecated model delegates.',
        'core/Mage/Directory/Model/Currency.php' =>
            'The deprecated delegates still answer third-party callers, from the same resource the helper reads.',
    ];
}

/**
 * @return array<int, array{file: string, line: int, match: string}>
 */
function rateAccessFindings(string $pattern, array $allowed): array
{
    $root = Mage::getBaseDir() . '/app/code/';
    $findings = [];

    $dir = new RecursiveDirectoryIterator(rtrim($root, '/'));
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace($root, '', $file->getPathname());
        if (isset($allowed[$relative])) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (preg_match($pattern, $line, $matches)) {
                $findings[] = ['file' => $relative, 'line' => $number + 1, 'match' => trim($matches[0])];
            }
        }
    }

    usort($findings, fn(array $a, array $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

    return $findings;
}

function rateAccessMessage(array $findings, string $rule): string
{
    return $rule . "\n" . implode("\n", array_map(
        fn(array $f) => sprintf('  %s:%d  %s', $f['file'], $f['line'], $f['match']),
        $findings,
    ));
}

it('leaves the rate table to its resource model', function () {
    $findings = rateAccessFindings('/directory[\/_]currency_rate(?!s)/', rateTableWriters());

    expect($findings)->toBe([], rateAccessMessage(
        $findings,
        'Read the rate table through Mage_Directory_Model_Resource_Currency, which owns it.',
    ));
});

it('leaves the rate resource model to the currency model', function () {
    $findings = rateAccessFindings(
        '/getResource(Model|Singleton)\(\s*[\'"]directory\/currency[\'"]/',
        rateResourceCallers(),
    );

    expect($findings)->toBe([], rateAccessMessage(
        $findings,
        'Ask Mage_Directory_Helper_Data for a rate; only the currency model reaches its resource.',
    ));
});
