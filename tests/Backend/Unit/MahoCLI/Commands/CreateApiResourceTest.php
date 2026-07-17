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
    // First-party modules (Mage_*/Maho_*) get the canonical project copyright holder
    expect($out)->toContain('SPDX-FileCopyrightText: ' . date('Y') . ' Maho <https://mahocommerce.com>')
        ->and($out)->toContain('SPDX-License-Identifier: OSL-3.0')
        ->and($out)->toContain('class Sample extends CrudResource')
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

it('honours --route and --section overrides', function () {
    $tester = createApiResourceTester();
    $tester->execute([
        '--module' => 'Mage_Cms',
        '--resource' => 'Sample',
        '--model' => 'cms/page',
        '--route' => '/custom/sample-routes',
        '--section' => 'Custom Section',
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $out = $tester->getDisplay();
    expect($out)->toContain("uriTemplate: '/custom/sample-routes',")
        ->and($out)->toContain("uriTemplate: '/custom/sample-routes/{id}',")
        ->and($out)->toContain("mahoSection: 'Custom Section',")
        // the permission id still comes from the short name, not the route
        ->and($out)->toContain("is_granted('samples/read')");
});

it('rejects options that are not quote-safe for the generated code', function (array $options) {
    $tester = createApiResourceTester();
    $tester->execute($options + [
        '--module' => 'Mage_Cms',
        '--resource' => 'Sample',
        '--model' => 'cms/page',
        '--dry-run' => true,
    ]);

    expect($tester->getStatusCode())->toBe(Command::INVALID);
})->with([
    'route with a quote' => [['--route' => "/foo's"]],
    'route with invalid chars' => [['--route' => '/Foo Bar']],
    'section with a quote' => [['--section' => "Bob's"]],
    'malformed module' => [['--module' => 'not a module']],
    'malformed resource' => [['--resource' => 'lowercase']],
    'malformed model alias' => [['--model' => "cms/page'"]],
]);

it('skips columns whose names are not valid PHP identifiers instead of emitting them', function () {
    // SQL allows nearly any character in quoted column names; such names must
    // never be interpolated into the generated PHP source.
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = 'cli_scaffold_hostile_cols_test';
    $quotedTable = $adapter->quoteIdentifier($table);
    $hostileCol = $adapter->quoteIdentifier('bad col = null; } eval');
    $adapter->query("DROP TABLE IF EXISTS {$quotedTable}");
    $adapter->query(
        "CREATE TABLE {$quotedTable} ("
        . 'entity_id INTEGER NOT NULL PRIMARY KEY, '
        . 'good_col VARCHAR(32) NULL, '
        . "{$hostileCol} VARCHAR(32) NULL"
        . ')',
    );

    try {
        $resourceStub = new class {
            public function getMainTable(): string
            {
                return 'cli_scaffold_hostile_cols_test';
            }
            public function getIdFieldName(): string
            {
                return 'entity_id';
            }
        };
        $modelStub = new class ($resourceStub) {
            public function __construct(private readonly object $resource) {}
            public function getResource(): object
            {
                return $this->resource;
            }
        };

        $command = new CreateApiResource();
        $buildProperties = new ReflectionMethod($command, 'buildProperties');
        [$properties, $note] = $buildProperties->invoke($command, $modelStub, 'test/hostile');

        expect($properties)->toContain('public ?string $goodCol = null;')
            ->and($properties)->not->toContain('eval')
            ->and($note)->toContain('not valid PHP identifiers');
    } finally {
        $adapter->query("DROP TABLE IF EXISTS {$quotedTable}");
    }
});

it('maps the DATA_TYPE names describeTable() actually emits to PHP types', function () {
    $command = new CreateApiResource();
    $mapType = new ReflectionMethod($command, 'mapType');

    // describeTable() derives DATA_TYPE from the Doctrine type class, so these
    // are Doctrine names: every MySQL tinyint introspects as 'boolean', and a
    // native FLOAT introspects as 'smallfloat'.
    expect($mapType->invoke($command, 'boolean'))->toBe('bool')
        ->and($mapType->invoke($command, 'integer'))->toBe('int')
        ->and($mapType->invoke($command, 'smallint'))->toBe('int')
        ->and($mapType->invoke($command, 'bigint'))->toBe('int')
        ->and($mapType->invoke($command, 'decimal'))->toBe('float')
        ->and($mapType->invoke($command, 'float'))->toBe('float')
        ->and($mapType->invoke($command, 'smallfloat'))->toBe('float')
        ->and($mapType->invoke($command, 'datetime'))->toBe('string')
        ->and($mapType->invoke($command, 'text'))->toBe('string')
        ->and($mapType->invoke($command, 'string'))->toBe('string')
        // spatial types must not false-match the int pattern
        ->and($mapType->invoke($command, 'point'))->toBe('string');

    $typeDefault = new ReflectionMethod($command, 'typeDefault');
    expect($typeDefault->invoke($command, 'bool'))->toBe('false')
        ->and($typeDefault->invoke($command, 'int'))->toBe('0')
        ->and($typeDefault->invoke($command, 'float'))->toBe('0.0')
        ->and($typeDefault->invoke($command, 'string'))->toBe("''");
});
