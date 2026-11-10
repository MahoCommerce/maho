<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\Pagination\TraversablePaginator;
use MahoCLI\Commands\CreateApiResource;
use MahoCLI\Commands\CreateService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;
use Symfony\Component\ObjectMapper\Attribute\Map;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

/**
 * dev:service:create and dev:api:resource:create --service write into a temporary module in the local code pool.
 * The test declares the module in the loaded config, and deletes the folder and the declaration after each test.
 */
const SERVICE_SCAFFOLD_MODULE = 'Acme_ScaffoldTest';
const SERVICE_SCAFFOLD_PATH_PREFIX = 'service-scaffold-test/';
const SERVICE_SCAFFOLD_CONFIG = <<<'XML'
    <?xml version="1.0"?>
    <config>
        <modules>
            <Acme_ScaffoldTest>
                <version>1.0.0</version>
            </Acme_ScaffoldTest>
        </modules>
        <global>
            <models>
                <scaffoldtest>
                    <class>Acme_ScaffoldTest_Model</class>
                </scaffoldtest>
            </models>
        </global>
    </config>

    XML;

function serviceScaffoldDir(): string
{
    return Mage::getConfig()->getOptions()->getCodeDir() . '/local/Acme/ScaffoldTest';
}

function serviceScaffoldRun(string $commandClass, array $input): ExecutionResult
{
    $command = new $commandClass();
    $application = new Application();
    $application->addCommand($command);
    return new CommandTester($command)->run($input, ['interactive' => false]);
}

function serviceScaffoldLint(string $file): void
{
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    expect($code)->toBe(0, implode("\n", $output));
}

/**
 * Merge the config.xml of the temporary module into the loaded config, as a cache flush does for a real module.
 */
function serviceScaffoldLoadConfig(): void
{
    $moduleConfig = new Mage_Core_Model_Config_Base();
    $moduleConfig->loadFile(serviceScaffoldDir() . '/etc/config.xml');
    Mage::getConfig()->extend($moduleConfig);
}

function serviceScaffoldCleanup(): void
{
    $dir = serviceScaffoldDir();
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
    foreach ([dirname($dir), dirname($dir, 2)] as $parent) {
        if (is_dir($parent) && (scandir($parent) ?: []) === ['.', '..']) {
            rmdir($parent);
        }
    }

    $config = Mage::getConfig();
    $modules = $config->getNode('modules');
    unset($modules->{SERVICE_SCAFFOLD_MODULE});
    $services = $config->getNode('global/services');
    if ($services) {
        unset($services->scaffoldtest);
    }
    Mage::unregister('_service/scaffoldtest/config_entry');

    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->delete(
        $resource->getTableName('core/config_data'),
        $adapter->quoteInto('path LIKE ?', SERVICE_SCAFFOLD_PATH_PREFIX . '%'),
    );
}

beforeEach(function (): void {
    serviceScaffoldCleanup();
    $config = Mage::getConfig();
    $config->setNode('modules/' . SERVICE_SCAFFOLD_MODULE . '/active', 'true');
    $config->setNode('modules/' . SERVICE_SCAFFOLD_MODULE . '/codePool', 'local');
    mkdir(serviceScaffoldDir() . '/etc', 0755, true);
    file_put_contents(serviceScaffoldDir() . '/etc/config.xml', SERVICE_SCAFFOLD_CONFIG);
});

afterEach(function (): void {
    serviceScaffoldCleanup();
});

describe('dev:service:create', function () {
    it('writes a service for a model, declares its class group, and the service resolves', function () {
        $result = serviceScaffoldRun(CreateService::class, [
            'module' => SERVICE_SCAFFOLD_MODULE,
            'noun' => 'Config_Entry',
            '--model' => 'core/config_data',
        ]);
        $this->assertCommandIsSuccessful($result);
        expect($result->getDisplay())->toContain("Mage::getService('scaffoldtest/config_entry')");

        $file = serviceScaffoldDir() . '/Service/Config/Entry.php';
        serviceScaffoldLint($file);
        $code = (string) file_get_contents($file);
        expect($code)->toContain('SPDX-FileCopyrightText: ' . date('Y') . ' Acme')
            ->and($code)->toContain('SPDX-License-Identifier: OSL-3.0')
            ->and($code)->toContain("declare(strict_types=1);\n\nclass Acme_ScaffoldTest_Service_Config_Entry\n")
            ->and($code)->toContain('public function newEntry(): Mage_Core_Model_Config_Data')
            ->and($code)->toContain('public function getById(int $id): Mage_Core_Model_Config_Data')
            ->and($code)->toContain('public function save(Mage_Core_Model_Config_Data $entry): Mage_Core_Model_Config_Data')
            ->and($code)->toContain('public function delete(Mage_Core_Model_Config_Data $entry): void');

        $xml = simplexml_load_file(serviceScaffoldDir() . '/etc/config.xml');
        expect((string) $xml->global->services->scaffoldtest->class)->toBe('Acme_ScaffoldTest_Service')
            ->and((string) $xml->global->models->scaffoldtest->class)->toBe('Acme_ScaffoldTest_Model');

        require_once $file;
        serviceScaffoldLoadConfig();
        $service = Mage::getService('scaffoldtest/config_entry');
        expect($service)->toBeInstanceOf('Acme_ScaffoldTest_Service_Config_Entry');

        $entry = $service->newEntry()
            ->setScope('default')
            ->setScopeId(0)
            ->setPath(SERVICE_SCAFFOLD_PATH_PREFIX . 'a/b')
            ->setValue('1');
        $service->save($entry);
        expect($service->getById((int) $entry->getId())->getPath())->toBe(SERVICE_SCAFFOLD_PATH_PREFIX . 'a/b');

        $service->delete($entry);
        expect(fn() => $service->getById((int) $entry->getId()))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class, 'This entry no longer exists.');
    });

    it('refuses a service that exists, unless --force, and declares the group once', function () {
        $input = ['module' => SERVICE_SCAFFOLD_MODULE, 'noun' => 'Config_Entry'];
        $this->assertCommandIsSuccessful(serviceScaffoldRun(CreateService::class, $input));
        $file = serviceScaffoldDir() . '/Service/Config/Entry.php';
        file_put_contents($file, '<?php // changed');

        $result = serviceScaffoldRun(CreateService::class, $input);
        $this->assertCommandFailed($result);
        expect($result->getDisplay())->toContain('--force')
            ->and(file_get_contents($file))->toBe('<?php // changed');

        $this->assertCommandIsSuccessful(serviceScaffoldRun(CreateService::class, $input + ['--force' => true]));
        serviceScaffoldLint($file);
        expect(file_get_contents($file))->toContain('class Acme_ScaffoldTest_Service_Config_Entry')
            ->and(substr_count((string) file_get_contents(serviceScaffoldDir() . '/etc/config.xml'), '<services>'))->toBe(1);
    });

    it('writes an empty service without a model, and adds a group to an existing services node', function () {
        file_put_contents(serviceScaffoldDir() . '/etc/config.xml', str_replace(
            '        <models>',
            "        <services>\n            <other>\n                <class>Acme_Other_Service</class>\n            </other>\n        </services>\n        <models>",
            SERVICE_SCAFFOLD_CONFIG,
        ));

        $this->assertCommandIsSuccessful(serviceScaffoldRun(CreateService::class, [
            'module' => SERVICE_SCAFFOLD_MODULE,
            'noun' => 'Message',
        ]));

        $file = serviceScaffoldDir() . '/Service/Message.php';
        serviceScaffoldLint($file);
        expect((string) file_get_contents($file))->toEndWith("class Acme_ScaffoldTest_Service_Message\n{\n}\n");

        $xml = simplexml_load_file(serviceScaffoldDir() . '/etc/config.xml');
        expect((string) $xml->global->services->other->class)->toBe('Acme_Other_Service')
            ->and((string) $xml->global->services->scaffoldtest->class)->toBe('Acme_ScaffoldTest_Service')
            ->and(count($xml->global->services))->toBe(1);
    });

    it('refuses an input of the wrong form, an unknown module and an unknown model', function (array $input, int $status) {
        $result = serviceScaffoldRun(CreateService::class, $input + ['module' => SERVICE_SCAFFOLD_MODULE, 'noun' => 'Message']);
        expect($result->statusCode)->toBe($status)
            ->and(is_dir(serviceScaffoldDir() . '/Service'))->toBeFalse();
    })->with([
        'module of the wrong form' => [['module' => 'acme'], Command::INVALID],
        'noun of the wrong form' => [['noun' => 'message'], Command::INVALID],
        'noun with a path' => [['noun' => '../Message'], Command::INVALID],
        'model alias of the wrong form' => [['--model' => "core/config_data'"], Command::INVALID],
        'module that is not declared' => [['module' => 'Acme_Missing'], Command::FAILURE],
        'model that does not exist' => [['--model' => 'scaffoldtest/missing'], Command::FAILURE],
    ]);
});

describe('dev:api:resource:create --service', function () {
    it('writes a resource whose provider and processor go through a new service', function () {
        $result = serviceScaffoldRun(CreateApiResource::class, [
            '--module' => SERVICE_SCAFFOLD_MODULE,
            '--resource' => 'ScaffoldConfigEntry',
            '--model' => 'core/config_data',
            '--service' => 'Config_Entry',
        ]);
        $this->assertCommandIsSuccessful($result);

        $apiDir = serviceScaffoldDir() . '/Api';
        $serviceFile = serviceScaffoldDir() . '/Service/Config/Entry.php';
        foreach (['ScaffoldConfigEntry', 'ScaffoldConfigEntryProvider', 'ScaffoldConfigEntryProcessor'] as $name) {
            serviceScaffoldLint("{$apiDir}/{$name}.php");
            require_once "{$apiDir}/{$name}.php";
        }
        serviceScaffoldLint($serviceFile);
        require_once $serviceFile;
        serviceScaffoldLoadConfig();

        $dtoClass = 'Acme\\ScaffoldTest\\Api\\ScaffoldConfigEntry';
        $map = new ReflectionClass($dtoClass)->getAttributes(Map::class)[0]->newInstance();
        expect($map->target)->toBe(Mage_Core_Model_Config_Data::class)
            ->and(new ReflectionProperty($dtoClass, 'path')->getAttributes(Map::class))->toHaveCount(1)
            ->and(new ReflectionProperty($dtoClass, 'path')->getType()?->allowsNull())->toBeTrue();

        $providerClass = 'Acme\\ScaffoldTest\\Api\\ScaffoldConfigEntryProvider';
        $processorClass = 'Acme\\ScaffoldTest\\Api\\ScaffoldConfigEntryProcessor';
        $provider = new $providerClass();
        $processor = new $processorClass();

        $entry = $provider->provide(new Post(uriTemplate: '/x'));
        expect($entry)->toBeInstanceOf(Mage_Core_Model_Config_Data::class)
            ->and($entry->getId())->toBeNull();
        $entry->setScope('default')->setScopeId(0)->setPath(SERVICE_SCAFFOLD_PATH_PREFIX . 'c/d')->setValue('1');
        $saved = $processor->process($entry, new Post(uriTemplate: '/x'));
        expect($saved->getId())->not->toBeNull();

        $loaded = $provider->provide(new Get(uriTemplate: '/x/{id}'), ['id' => (int) $saved->getId()]);
        expect($loaded->getPath())->toBe(SERVICE_SCAFFOLD_PATH_PREFIX . 'c/d');
        $loaded->setPath(SERVICE_SCAFFOLD_PATH_PREFIX . 'e/f');
        $processor->process($loaded, new Patch(uriTemplate: '/x/{id}'));
        expect(Mage::getModel('core/config_data')->load($saved->getId())->getPath())->toBe(SERVICE_SCAFFOLD_PATH_PREFIX . 'e/f');

        $page = $provider->provide(new GetCollection(uriTemplate: '/x'), [], ['filters' => ['pageSize' => 2]]);
        expect($page)->toBeInstanceOf(TraversablePaginator::class)
            ->and(iterator_to_array($page))->toHaveCount(2);

        expect($processor->process($loaded, new Delete(uriTemplate: '/x/{id}')))->toBeNull()
            ->and(fn() => $provider->provide(new Get(uriTemplate: '/x/{id}'), ['id' => (int) $saved->getId()]))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('keeps a service that exists', function () {
        $serviceFile = serviceScaffoldDir() . '/Service/Config/Entry.php';
        mkdir(dirname($serviceFile), 0755, true);
        file_put_contents($serviceFile, '<?php // kept');

        $result = serviceScaffoldRun(CreateApiResource::class, [
            '--module' => SERVICE_SCAFFOLD_MODULE,
            '--resource' => 'ScaffoldConfigEntry',
            '--model' => 'core/config_data',
            '--service' => 'Config_Entry',
            '--force' => true,
        ]);

        $this->assertCommandIsSuccessful($result);
        expect(file_get_contents($serviceFile))->toBe('<?php // kept')
            ->and(is_file(serviceScaffoldDir() . '/Api/ScaffoldConfigEntryProcessor.php'))->toBeTrue()
            ->and((string) simplexml_load_file(serviceScaffoldDir() . '/etc/config.xml')->global->services->scaffoldtest->class)
            ->toBe('Acme_ScaffoldTest_Service');
    });
});
