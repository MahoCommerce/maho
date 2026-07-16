<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CreateApiResource;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the dev:api:resource:create scaffolder (dry-run only, no writes).
 *
 * Exercises the three behaviours that carry real logic: flat-table column
 * introspection into typed DTO properties, the EAV stub fallback, and the
 * permission-id derivation that delegates to the compiler's inflector (so an
 * irregular short name resolves correctly and its security expression matches
 * the id the compiler will register).
 */
function createApiResourceTester(): CommandTester
{
    $command = new CreateApiResource();
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command);
}

it('scaffolds a CRUD resource from a flat-table model with introspected properties', function () {
    $tester = createApiResourceTester();
    $tester->execute([
        '--module' => 'Mage_Cms',
        '--resource' => 'Sample',
        '--model' => 'cms/page',
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $out = $tester->getDisplay();
    expect($out)->toContain('class Sample extends CrudResource')
        ->and($out)->toContain("public const MODEL = 'cms/page';")
        ->and($out)->toContain("uriTemplate: '/samples',")
        ->and($out)->toContain("uriTemplate: '/samples/{id}',")
        ->and($out)->toContain("is_granted('samples/read')")
        ->and($out)->toContain("is_granted('samples/write')")
        ->and($out)->toContain("is_granted('samples/delete')")
        // introspected from the cms_page table
        ->and($out)->toContain('public ?string $title = null;')
        // the identifier is always emitted and the PK column is not duplicated
        ->and($out)->toContain('#[ApiProperty(identifier: true, writable: false)]')
        ->and($out)->toContain('final class SampleProvider extends CrudProvider');
});

it('derives the permission id via the compiler inflector for irregular names', function () {
    $tester = createApiResourceTester();
    $tester->execute([
        '--module' => 'Mage_Cms',
        '--resource' => 'Person',
        '--model' => 'cms/page',
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $out = $tester->getDisplay();
    // 'Person' pluralises to 'people' (inflector), not 'persons' (old naive rule)
    expect($out)->toContain("uriTemplate: '/people',")
        ->and($out)->toContain("is_granted('people/read')")
        ->and($out)->not->toContain('persons');
});

it('generates a processor stub and wires the operations to it with --with-processor', function () {
    $tester = createApiResourceTester();
    $tester->execute([
        '--module' => 'Mage_Cms',
        '--resource' => 'Sample',
        '--model' => 'cms/page',
        '--with-processor' => true,
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $out = $tester->getDisplay();
    expect($out)->toContain('final class SampleProcessor extends CrudProcessor')
        // the operations reference the generated processor, not the shared one
        ->and($out)->toContain('processor: SampleProcessor::class,')
        ->and($out)->not->toContain('processor: CrudProcessor::class,');
});

it('falls back to a property stub for EAV entities', function () {
    $tester = createApiResourceTester();
    $tester->execute([
        '--module' => 'Maho_Blog',
        '--resource' => 'Sample',
        '--model' => 'blog/post',
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $out = $tester->getDisplay();
    expect($out)->toContain('EAV entity')
        ->and($out)->toContain('TODO: declare typed public properties');
});
