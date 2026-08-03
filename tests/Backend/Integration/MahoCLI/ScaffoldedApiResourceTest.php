<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\Discovery\ModuleApiDiscovery;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\Config\ApiResource;
use MahoCLI\Commands\CreateApiResource;
use MahoCLI\Commands\ListApiResources;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

uses(Tests\MahoBackendTestCase::class);

/**
 * End-to-end coverage for dev:api:resource:create beyond --dry-run:
 *
 *  1. Files are actually written (including creating the Api/ directory),
 *     are syntactically valid PHP, and the collision/--force guards work.
 *  2. A scaffolded resource round-trips through discovery: after a cache
 *     flush, dev:api:resource:list reports it with the expected route,
 *     methods and permission id.
 *  3. The generated write API works against the real database: create,
 *     update and delete persist through the shared CrudProcessor, reads
 *     come back through the generated provider, and the derived permission
 *     ids are declared on the write operations' security expressions, the
 *     declarative gate the access checker enforces at request time.
 *
 * Scaffolds into Mage_Log (a module with no Api/ directory, so the mkdir
 * branch is exercised) against core/config_data (a flat table with no
 * store-scope side tables, so rows are trivial to clean up).
 */
const SCAFFOLD_SMOKE_MODULE = 'Mage_Log';
const SCAFFOLD_SMOKE_RESOURCE = 'CliSmokeConfigEntry';
const SCAFFOLD_SMOKE_DTO = 'Mage\\Log\\Api\\CliSmokeConfigEntry';
const SCAFFOLD_SMOKE_PROVIDER = 'Mage\\Log\\Api\\CliSmokeConfigEntryProvider';
const SCAFFOLD_SMOKE_ID = 'cli-smoke-config-entries';
const SCAFFOLD_SMOKE_PATH_PREFIX = 'cli-scaffold-test/';

function scaffoldSmokeApiDir(): string
{
    return Mage::getConfig()->getModuleDir('', SCAFFOLD_SMOKE_MODULE) . '/Api';
}

function scaffoldSmokeRun(array $extraInput = []): CommandTester
{
    $command = new CreateApiResource();
    $application = new Application();
    $application->addCommand($command);
    $tester = new CommandTester($command);
    $tester->execute($extraInput + [
        '--module' => SCAFFOLD_SMOKE_MODULE,
        '--resource' => SCAFFOLD_SMOKE_RESOURCE,
        '--model' => 'core/config_data',
    ]);
    return $tester;
}

/** Build a Security whose current user is the given ApiUser, as the kernel would. */
function scaffoldSmokeSecurity(ApiUser $user): Security
{
    $tokenStorage = new TokenStorage();
    $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    $container = new Container();
    $container->set('security.token_storage', $tokenStorage);
    return new Security($container);
}

function scaffoldSmokeCleanup(): void
{
    foreach (glob(scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . '*.php') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir(scaffoldSmokeApiDir()) && (glob(scaffoldSmokeApiDir() . '/*') ?: []) === []) {
        rmdir(scaffoldSmokeApiDir());
    }
    ModuleApiDiscovery::clearCache();

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete(
        $resource->getTableName('core/config_data'),
        $adapter->quoteInto('path LIKE ?', SCAFFOLD_SMOKE_PATH_PREFIX . '%'),
    );
}

// Cleanup runs before each test too, so debris from a hard-killed earlier run
// can't fail the next one.
beforeEach(function (): void {
    scaffoldSmokeCleanup();

    // These tests assume Mage_Log ships without an Api/ directory (that's what
    // exercises the mkdir branch). If it ever gains one, pick another module.
    if (is_dir(scaffoldSmokeApiDir())) {
        $this->markTestSkipped(SCAFFOLD_SMOKE_MODULE . ' now has an Api/ directory; update SCAFFOLD_SMOKE_MODULE to a module without one.');
    }
});

afterEach(function (): void {
    scaffoldSmokeCleanup();
});

it('writes syntactically valid files to disk and enforces the overwrite guard', function () {
    expect(is_dir(scaffoldSmokeApiDir()))->toBeFalse();

    $tester = scaffoldSmokeRun();
    expect($tester->getStatusCode())->toBe(Command::SUCCESS);

    $files = [
        scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . '.php',
        scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . 'Provider.php',
    ];
    foreach ($files as $file) {
        expect(is_file($file))->toBeTrue();
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lintOutput, $lintCode);
        expect($lintCode)->toBe(0);
    }

    // Re-running without --force must refuse to overwrite
    $tester = scaffoldSmokeRun();
    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('already exists');

    // --force overwrites, and a --with-processor run adds a valid processor file
    $tester = scaffoldSmokeRun(['--force' => true, '--with-processor' => true]);
    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
    $processorFile = scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . 'Processor.php';
    expect(is_file($processorFile))->toBeTrue();
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($processorFile) . ' 2>&1', $lintOutput, $lintCode);
    expect($lintCode)->toBe(0);
});

it('is picked up by discovery and the lister after a cache flush', function () {
    scaffoldSmokeRun();
    ModuleApiDiscovery::clearCache();

    $command = new ListApiResources();
    $application = new Application();
    $application->addCommand($command);
    $tester = new CommandTester($command);
    $tester->execute(['--module' => SCAFFOLD_SMOKE_MODULE, '--json' => true]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);

    $rows = json_decode(trim($tester->getDisplay()), true);
    $byResource = array_column($rows, null, 'resource');
    expect($byResource)->toHaveKey(SCAFFOLD_SMOKE_RESOURCE);

    $row = $byResource[SCAFFOLD_SMOKE_RESOURCE];
    expect($row['module'])->toBe(SCAFFOLD_SMOKE_MODULE)
        ->and($row['id'])->toBe(SCAFFOLD_SMOKE_ID)
        ->and($row['route'])->toBe('/' . SCAFFOLD_SMOKE_ID)
        ->and($row['methods'])->toBe(['GET', 'POST', 'PUT', 'DELETE'])
        ->and($row['graphql'])->toBeTrue()
        // Not in the compiled permission registry yet → admin-gated, not public
        ->and($row['access'])->toBe('admin');
});

it('persists creates, updates and deletes to the database through the generated API', function () {
    scaffoldSmokeRun();
    require_once scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . 'Provider.php';
    require_once scaffoldSmokeApiDir() . '/' . SCAFFOLD_SMOKE_RESOURCE . '.php';
    expect(class_exists(SCAFFOLD_SMOKE_DTO, false))->toBeTrue()
        ->and(class_exists(SCAFFOLD_SMOKE_PROVIDER, false))->toBeTrue();

    // Non-admin API user carrying exactly the permission ids the scaffolder derived
    $user = new ApiUser('cli-scaffold-smoke', ['ROLE_API'], null, null, 424242, [
        SCAFFOLD_SMOKE_ID . '/write',
        SCAFFOLD_SMOKE_ID . '/delete',
    ]);
    $security = scaffoldSmokeSecurity($user);
    $processor = new CrudProcessor($security);
    $dtoClass = SCAFFOLD_SMOKE_DTO;

    // CREATE: the row must land in core_config_data
    $dto = new $dtoClass();
    $dto->scope = 'default';
    $dto->scopeId = 0;
    $dto->path = SCAFFOLD_SMOKE_PATH_PREFIX . 'group/field';
    $dto->value = 'created-by-test';
    $post = (new Post(uriTemplate: '/' . SCAFFOLD_SMOKE_ID, name: '_api_smoke_post'))->withClass($dtoClass);
    $created = $processor->process($dto, $post);

    expect($created)->toBeInstanceOf($dtoClass)
        ->and($created->id)->toBeGreaterThan(0)
        ->and($created->value)->toBe('created-by-test');

    $row = Mage::getModel('core/config_data')->load($created->id);
    expect((int) $row->getId())->toBe($created->id)
        ->and($row->getPath())->toBe(SCAFFOLD_SMOKE_PATH_PREFIX . 'group/field')
        ->and($row->getValue())->toBe('created-by-test');

    // UPDATE: the change must be persisted, not just echoed back
    $dto->value = 'updated-by-test';
    $put = (new Put(uriTemplate: '/' . SCAFFOLD_SMOKE_ID . '/{id}', name: '_api_smoke_put'))->withClass($dtoClass);
    $updated = $processor->process($dto, $put, ['id' => $created->id]);

    expect($updated->value)->toBe('updated-by-test');
    $row = Mage::getModel('core/config_data')->load($created->id);
    expect($row->getValue())->toBe('updated-by-test');

    // READ back through the generated provider
    $providerClass = SCAFFOLD_SMOKE_PROVIDER;
    $provider = new $providerClass($security);
    $get = (new Get(uriTemplate: '/' . SCAFFOLD_SMOKE_ID . '/{id}', name: '_api_smoke_get'))->withClass($dtoClass);
    $fetched = $provider->provide($get, ['id' => $created->id]);

    expect($fetched)->toBeInstanceOf($dtoClass)
        ->and($fetched->id)->toBe($created->id)
        ->and($fetched->value)->toBe('updated-by-test');

    // The derived permission ids gate writes declaratively, via the operations'
    // security expressions (enforced at request time by the access checker).
    $securityByOperation = [];
    $apiResource = (new ReflectionClass($dtoClass))
        ->getAttributes(ApiResource::class)[0]->newInstance();
    foreach ($apiResource->getOperations() as $operation) {
        $securityByOperation[$operation::class] = (string) $operation->getSecurity();
    }
    expect($securityByOperation[Post::class])->toContain("is_granted('" . SCAFFOLD_SMOKE_ID . "/write')")
        ->and($securityByOperation[Put::class])->toContain("is_granted('" . SCAFFOLD_SMOKE_ID . "/write')")
        ->and($securityByOperation[Delete::class])->toContain("is_granted('" . SCAFFOLD_SMOKE_ID . "/delete')");

    // DELETE: the row must be gone from the database
    $delete = (new Delete(uriTemplate: '/' . SCAFFOLD_SMOKE_ID . '/{id}', name: '_api_smoke_delete'))->withClass($dtoClass);
    $processor->process(null, $delete, ['id' => $created->id]);

    $row = Mage::getModel('core/config_data')->load($created->id);
    expect($row->getId())->toBeEmpty();
});
