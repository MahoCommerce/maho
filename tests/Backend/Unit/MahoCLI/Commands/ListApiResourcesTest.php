<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\ListApiResources;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the dev:api:resource:list command.
 *
 * Drives real module discovery + attribute reflection + permission-registry
 * merge, asserting a known core resource resolves to the expected module,
 * route, HTTP methods and permission id.
 */
function listApiResourcesTester(): CommandTester
{
    $command = new ListApiResources();
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

it('lists discovered resources as JSON with resolved routes and permission ids', function () {
    $tester = listApiResourcesTester();
    $tester->execute(['--module' => 'Maho_Blog', '--json' => true]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);

    $data = json_decode(trim($tester->getDisplay()), true);
    expect($data)->toBeArray()->not->toBeEmpty();

    $byResource = [];
    foreach ($data as $row) {
        $byResource[$row['resource']] = $row;
    }

    expect($byResource)->toHaveKey('BlogPost');
    $post = $byResource['BlogPost'];
    expect($post['module'])->toBe('Maho_Blog')
        ->and($post['id'])->toBe('blog-posts')
        ->and($post['route'])->toBe('/blog-posts')
        ->and($post['graphql'])->toBeTrue()
        ->and($post['methods'])->toContain('GET')
        ->and($post['methods'])->toContain('POST')
        ->and($post['methods'])->toContain('PUT')
        ->and($post['methods'])->toContain('DELETE');
});

it('only returns resources from the requested module', function () {
    $tester = listApiResourcesTester();
    $tester->execute(['--module' => 'Maho_Blog', '--json' => true]);

    $data = json_decode(trim($tester->getDisplay()), true);
    $modules = array_unique(array_column($data, 'module'));

    expect($modules)->toBe(['Maho_Blog']);
});

it('renders a table by default', function () {
    $tester = listApiResourcesTester();
    $tester->execute(['--module' => 'Maho_Blog']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('BlogPost')
        ->and($tester->getDisplay())->toContain('/blog-posts');
});
