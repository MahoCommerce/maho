<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CreateCommand;

uses(Tests\MahoBackendTestCase::class);

it('generates valid PHP for a description with quotes and backslashes', function (): void {
    $method = new ReflectionMethod(CreateCommand::class, 'generateCommandContent');
    $description = "Don't purge the cache\\path";

    $content = $method->invoke(new CreateCommand('dev:create-command'), 'CacheCleanCommand', 'cache:clean', $description);

    PhpToken::tokenize($content, TOKEN_PARSE);
    expect($content)->toContain('description: ' . var_export($description, true))
        ->not->toContain('Attribute\Ask;');
});
