<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

uses(Tests\MahoBackendTestCase::class);

// In a Composer project CommandDiscoverer skips vendor/mahocommerce/maho, so a core
// command the maho script does not register is missing from ./maho
it('registers every core command in the maho script', function () {
    preg_match_all('/new \\\\MahoCLI\\\\Commands\\\\(\w+)\(\)/', file_get_contents(Mage::getBaseDir() . '/maho'), $matches);
    $registered = $matches[1];

    $commands = [];
    foreach (glob(Mage::getBaseDir() . '/lib/MahoCLI/Commands/*.php') as $file) {
        $class = new ReflectionClass('MahoCLI\\Commands\\' . basename($file, '.php'));
        if ($class->isInstantiable() && $class->isSubclassOf(Command::class)) {
            $commands[] = $class->getShortName();
        }
    }

    expect($commands)->not->toBeEmpty()
        ->and(array_values(array_diff($commands, $registered)))->toBe([]);
});
