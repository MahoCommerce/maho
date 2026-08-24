<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\HealthCheck;
use MahoCLI\Helper\CallbackResolver;
use MahoCLI\Helper\PhantomMethodScanner;
use MahoCLI\Helper\StaleModuleVersionScanner;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the health checks that find database state left behind by a module
 * whose code is gone: phantom payment methods and carriers, unclaimed config
 * sections, stale version records, unclaimed tables, and stale attribute
 * registrations. A disabled module is not residue, so each rule keeps its own
 * bucket for one, and the purge never touches it.
 */

/**
 * Run $assert with $rows (path => value) in core_config_data, then put the table back.
 *
 * @param array<string, string> $rows
 */
function residueWithConfig(array $rows, callable $assert): void
{
    $config = Mage::getConfig();
    foreach ($rows as $path => $value) {
        $config->saveConfig($path, $value);
    }
    $config->reinit();

    try {
        $assert();
    } finally {
        foreach (array_keys($rows) as $path) {
            $config->deleteConfig($path);
        }
        $config->reinit();
    }
}

/**
 * Declare a disabled module that ships $files (path relative to the module root),
 * run $assert, then delete every file and directory it created.
 *
 * @param array<string, string> $files
 */
function residueWithDisabledModule(string $module, array $files, callable $assert): void
{
    $declaration = Mage::getBaseDir('etc') . "/modules/{$module}.xml";
    $base = Mage::getBaseDir('code') . '/local/' . str_replace('_', '/', $module);

    $created = [];
    foreach ($files as $relative => $contents) {
        $file = "{$base}/{$relative}";
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0o777, true);
        }
        file_put_contents($file, $contents);
        $created[] = $file;
    }
    file_put_contents($declaration, sprintf(
        '<?xml version="1.0"?><config><modules><%s><active>false</active><codePool>local</codePool></%s></modules></config>',
        $module,
        $module,
    ));

    Mage::getConfig()->reinit();

    try {
        $assert();
    } finally {
        unlink($declaration);
        foreach ($created as $file) {
            unlink($file);
        }
        // Deepest first, and only the directories this fixture created.
        $directories = array_unique(array_map(dirname(...), $created));
        usort($directories, fn(string $a, string $b) => strlen($b) <=> strlen($a));
        foreach ([...$directories, $base, dirname($base)] as $directory) {
            @rmdir($directory);
        }
        Mage::getConfig()->reinit();
    }
}

/**
 * Create $table with a single id column, run $assert, then drop it.
 */
function residueWithTable(string $table, callable $assert): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->createTable(
        $adapter->newTable($table)->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [], 'Id'),
    );

    try {
        $assert();
    } finally {
        $adapter->dropTable($table);
    }
}

/**
 * Insert a core_resource row, run $assert, then delete the row.
 */
function residueWithResourceVersion(string $code, callable $assert): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('core/resource');
    $adapter->insert($table, ['code' => $code, 'version' => '1.0.0', 'data_version' => '1.0.0']);

    try {
        $assert();
    } finally {
        $adapter->delete($table, ['code = ?' => $code]);
    }
}

it('reports no residue on a healthy install', function () {
    expect(HealthCheck::findPhantomMethods())->toBe([])
        ->and(HealthCheck::findUnclaimedConfigSections())->toBe(['unclaimed' => [], 'disabled' => []])
        ->and(HealthCheck::findStaleResourceVersions())->toBe(['stale' => [], 'disabled' => []])
        ->and(HealthCheck::findUnclaimedTables())->toBe(['unclaimed' => [], 'disabled' => []])
        ->and(HealthCheck::findStaleRegistrations())->toBe([]);
});

it('flags an active payment method whose model class is gone', function () {
    $code = 'residue_' . uniqid();

    residueWithConfig(["payment/{$code}/active" => '1'], function () use ($code) {
        $findings = HealthCheck::findPhantomMethods();
        $finding = current(array_filter($findings, fn(array $f) => $f['code'] === $code));

        expect($finding)->not->toBeFalse()
            ->and($finding['section'])->toBe('payment')
            ->and($finding['active'])->toBeTrue()
            ->and($finding['reason'])->toContain('no model')
            ->and($finding['paths'])->toBe(["payment/{$code}/active"]);
    });
});

it('flags a shipping carrier whose model class is gone', function () {
    $code = 'residue_' . uniqid();

    residueWithConfig(["carriers/{$code}/active" => '1'], function () use ($code) {
        $codes = array_column(HealthCheck::findPhantomMethods(), 'code');

        expect($codes)->toContain($code);
    });
});

it('leaves an installed payment method alone', function () {
    $codes = array_column(HealthCheck::findPhantomMethods(), 'code');

    expect($codes)->not->toContain('checkmo')
        ->and($codes)->not->toContain('flatrate');
});

it('purges the configuration rows of a phantom method', function () {
    $code = 'residue_' . uniqid();
    $path = "payment/{$code}/active";

    residueWithConfig([$path => '1'], function () use ($code, $path) {
        expect(array_column(HealthCheck::findPhantomMethods(), 'code'))->toContain($code);

        $deleted = PhantomMethodScanner::purge([$path]);
        Mage::getConfig()->reinit();

        expect($deleted)->toBe(1)
            ->and(array_column(HealthCheck::findPhantomMethods(), 'code'))->not->toContain($code);
    });
});

it('flags a config section that no installed module declares', function () {
    $section = 'residue' . uniqid();

    residueWithConfig(["{$section}/group/field" => '1'], function () use ($section) {
        $findings = HealthCheck::findUnclaimedConfigSections();
        $finding = current(array_filter($findings['unclaimed'], fn(array $f) => $f['section'] === $section));

        expect($finding)->not->toBeFalse()
            ->and($finding['rows'])->toBe(1);
    });
});

it('leaves a config section of an installed module alone', function () {
    $sections = array_column(HealthCheck::findUnclaimedConfigSections()['unclaimed'], 'section');

    expect($sections)->not->toContain('web')
        ->and($sections)->not->toContain('catalog');
});

it('flags a version record of a setup resource no module ships', function () {
    $code = 'residue' . uniqid() . '_setup';

    residueWithResourceVersion($code, function () use ($code) {
        $findings = HealthCheck::findStaleResourceVersions();

        expect(array_column($findings['stale'], 'code'))->toContain($code);
    });
});

it('never flags the version record of the declarative schema', function () {
    $findings = HealthCheck::findStaleResourceVersions();

    expect(array_column($findings['stale'], 'code'))->not->toContain(\Maho\Db\Schema\Status::RESOURCE_CODE);
});

it('purges a stale version record', function () {
    $code = 'residue' . uniqid() . '_setup';

    residueWithResourceVersion($code, function () use ($code) {
        expect(array_column(HealthCheck::findStaleResourceVersions()['stale'], 'code'))->toContain($code);

        $deleted = StaleModuleVersionScanner::purge([$code]);

        expect($deleted)->toBe(1)
            ->and(array_column(HealthCheck::findStaleResourceVersions()['stale'], 'code'))->not->toContain($code);
    });
});

it('flags a table that no installed module declares', function () {
    $table = 'residue' . uniqid();

    residueWithTable($table, function () use ($table) {
        expect(HealthCheck::findUnclaimedTables()['unclaimed'])->toContain($table);
    });
});

it('leaves a declared table alone', function () {
    $unclaimed = HealthCheck::findUnclaimedTables()['unclaimed'];

    expect($unclaimed)->not->toContain(Mage::getSingleton('core/resource')->getTableName('core/config_data'))
        ->and($unclaimed)->not->toContain(Mage::getSingleton('core/resource')->getTableName('catalog/product'));
});

it('keeps the residue of a disabled module out of the purge lists', function () {
    $module = 'Residue_Probe';
    $section = 'residueprobe';
    $files = [
        'etc/config.xml' => sprintf(
            '<?xml version="1.0"?><config><global><models><%1$s><entities><thing><table>%1$s_thing</table>'
            . '</thing></entities></%1$s></models></global><default><%1$s><group><field>1</field></group>'
            . '</%1$s></default></config>',
            $section,
        ),
        'sql/schema.php' => "<?php return function (\$schema) { \$schema->createTable('{$section}_schema'); };\n",
        "sql/{$section}_setup/.gitkeep" => '',
    ];

    $assert = function () use ($module, $section) {
        $sections = HealthCheck::findUnclaimedConfigSections();
        $resources = HealthCheck::findStaleResourceVersions();
        $tables = HealthCheck::findUnclaimedTables();

        expect(array_column($sections['unclaimed'], 'section'))->not->toContain($section)
            ->and(array_column($sections['disabled'], 'section'))->toContain($section)
            ->and(array_column($sections['disabled'], 'module'))->toContain($module)
            ->and(array_column($resources['stale'], 'code'))->not->toContain("{$section}_setup")
            ->and(array_column($resources['disabled'], 'code'))->toContain("{$section}_setup")
            ->and($tables['unclaimed'])->not->toContain("{$section}_schema")
            ->and(array_column($tables['disabled'], 'table'))->toContain("{$section}_schema");
    };

    residueWithDisabledModule($module, $files, fn() => residueWithConfig(
        ["{$section}/group/field" => '1'],
        fn() => residueWithResourceVersion(
            "{$section}_setup",
            fn() => residueWithTable("{$section}_schema", $assert),
        ),
    ));
});

it('leaves a table a disabled module declares as an entity alone', function () {
    $module = 'Residue_Probe';
    $section = 'residueprobe';
    $files = [
        'etc/config.xml' => sprintf(
            '<?xml version="1.0"?><config><global><models><%1$s><entities><thing><table>%1$s_thing</table>'
            . '</thing></entities></%1$s></models></global></config>',
            $section,
        ),
    ];

    residueWithDisabledModule($module, $files, function () use ($section) {
        residueWithTable("{$section}_thing", function () use ($section) {
            $tables = HealthCheck::findUnclaimedTables();

            expect($tables['unclaimed'])->not->toContain("{$section}_thing")
                ->and(array_column($tables['disabled'], 'table'))->not->toContain("{$section}_thing");
        });
    });
});

it('reports why a callback cannot be resolved', function () {
    expect(CallbackResolver::findMissingCallback('core/nosuchmodel', 'run'))->toContain('does not exist')
        ->and(CallbackResolver::findMissingModel('core/nosuchmodel'))->toContain('does not exist')
        ->and(CallbackResolver::findMissingModel(''))->toContain('no model')
        ->and(CallbackResolver::findMissingCallback('core/config', 'noSuchMethod'))->toContain('does not exist')
        ->and(CallbackResolver::findMissingCallback('core/config', 'reinit'))->toBeNull();
});
