<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use Mage\Core\Api\ConfigSettingProcessor;
use Mage\Core\Api\ConfigSettingProvider;
use MahoCLI\Commands\ConfigDelete;
use MahoCLI\Commands\ConfigSet;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * System > Configuration, the config setting API and the config:* commands write through Mage_Core_Service_Config (#1348).
 */

const CONFIG_PARITY_NAME = 'general/store_information/name';
const CONFIG_PARITY_SECRET = 'system/smtp/password';
const CONFIG_PARITY_DEFAULT_ONLY = 'web/url/use_store';
const CONFIG_PARITY_STREET = 'customer/address/street_lines';

/**
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function configParityApi(string $method, string $path, array $body = [], array $query = []): array
{
    $request = AdminApiParity::jsonRequest(
        $method,
        "/api/rest/v2/config-settings/{$path}" . ($query === [] ? '' : '?' . http_build_query($query)),
        $body,
    );
    $security = AdminApiParity::adminSecurity();
    $operation = $method === 'DELETE' ? new Delete() : new Put();

    return AdminApiParity::api($request, fn() => new ConfigSettingProcessor($security, new ConfigSettingProvider($security))
        ->process(null, $operation, ['path' => $path], ['request' => $request]));
}

/**
 * @param array<string, mixed> $input
 * @return array{code: int, output: string}
 */
function configParityCli(Command $command, array $input): array
{
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute($input, ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

/**
 * Save $value of the field $field in the group $group of $section in System > Configuration.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function configParityAdmin(string $section, string $group, string $field, string $value, string $website = ''): array
{
    return AdminApiParity::admin(Mage_Adminhtml_System_ConfigController::class, 'save', [
        'section' => $section,
        'website' => $website,
        'groups' => [$group => ['fields' => [$field => ['value' => $value]]]],
    ]);
}

function configParityStored(string $path, string $scope = 'default', int $scopeId = 0): string|false
{
    return Mage::getConfig()->getConfig($path, $scope, $scopeId);
}

function configParityStreetLines(int $websiteId): ?string
{
    $resource = Mage::getSingleton('core/resource');
    $read = $resource->getConnection('core_read');
    $value = $read->fetchOne($read->select()
        ->from($resource->getTableName('customer/eav_attribute_website'), ['multiline_count'])
        ->where('attribute_id = ?', (int) Mage::getSingleton('eav/config')->getAttribute('customer_address', 'street')->getId())
        ->where('website_id = ?', $websiteId));
    return $value === false || $value === null ? null : (string) $value;
}

beforeEach(function (): void {
    $this->admin = AdminRoleFixture::login(['all']);
    $this->website = Mage::app()->getWebsite(true);
    $this->store = Mage::app()->getDefaultStoreView();
    $this->originalName = configParityStored(CONFIG_PARITY_NAME);
});

afterEach(function (): void {
    AdminRoleFixture::delete($this->admin);
    $config = Mage::getConfig();
    $this->originalName === false
        ? $config->deleteConfig(CONFIG_PARITY_NAME)
        : $config->saveConfig(CONFIG_PARITY_NAME, $this->originalName);
    $config->deleteConfig(CONFIG_PARITY_SECRET);
    $config->deleteConfig(CONFIG_PARITY_DEFAULT_ONLY, 'stores', (int) $this->store->getId());
    $config->deleteConfig(CONFIG_PARITY_STREET, 'websites', (int) $this->website->getId());
    $config->reinit();
    Mage::app()->reinitStores();
});

it('reloads the configuration after a save on every transport', function (): void {
    // The sample data sets the name for each website, so read the admin store, which takes the default value
    $adminStore = Mage_Core_Model_App::ADMIN_STORE_ID;
    configParityAdmin('general', 'store_information', 'name', 'Admin Name');
    expect(Mage::getStoreConfig(CONFIG_PARITY_NAME, $adminStore))->toBe('Admin Name');

    expect(configParityApi('PUT', CONFIG_PARITY_NAME, ['value' => 'Api Name'])['status'])->toBe(200)
        ->and(Mage::getStoreConfig(CONFIG_PARITY_NAME, $adminStore))->toBe('Api Name');

    $cli = configParityCli(new ConfigSet(), ['path' => CONFIG_PARITY_NAME, 'value' => 'Cli Name', '--scope' => 'default', '--scope-id' => 0]);
    expect($cli['code'])->toBe(Command::SUCCESS)
        ->and(Mage::getStoreConfig(CONFIG_PARITY_NAME, $adminStore))->toBe('Cli Name');
});

it('encrypts a secret on every transport', function (): void {
    $saves = [
        'admin' => fn() => configParityAdmin('system', 'smtp', 'password', 'admin-secret'),
        'api' => fn() => configParityApi('PUT', CONFIG_PARITY_SECRET, ['value' => 'api-secret']),
        'cli' => fn() => configParityCli(new ConfigSet(), ['path' => CONFIG_PARITY_SECRET, 'value' => 'cli-secret', '--scope' => 'default', '--scope-id' => 0]),
    ];
    foreach ($saves as $transport => $save) {
        $save();
        $stored = (string) configParityStored(CONFIG_PARITY_SECRET);
        expect($stored)->not->toBe("{$transport}-secret")
            ->and(Mage::helper('core')->decrypt($stored))->toBe("{$transport}-secret");
    }
});

it('refuses a field at a scope where System > Configuration does not show it, on the API and the CLI', function (): void {
    $api = configParityApi('PUT', CONFIG_PARITY_DEFAULT_ONLY, ['value' => '1', 'scope' => 'stores', 'scopeCode' => $this->store->getCode()]);
    $cli = configParityCli(new ConfigSet(), [
        'path' => CONFIG_PARITY_DEFAULT_ONLY, 'value' => '1', '--scope' => 'stores', '--scope-id' => (int) $this->store->getId(),
    ]);

    $message = "Field 'web/url/use_store' cannot be set at scope 'stores'";
    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['scope'])
        ->and($api['errors'])->toBe([$message])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message)
        ->and(configParityStored(CONFIG_PARITY_DEFAULT_ONLY, 'stores', (int) $this->store->getId()))->toBeFalse();
});

it('deletes a website value through the backend model of the field on the API and the CLI', function (): void {
    $websiteId = (int) $this->website->getId();
    /** @var Mage_Core_Service_Config $service */
    $service = Mage::getService('core/config');

    $service->save(CONFIG_PARITY_STREET, '3', 'websites', $websiteId);
    expect(configParityStreetLines($websiteId))->toBe('3');
    $api = configParityApi('DELETE', CONFIG_PARITY_STREET, query: ['scope' => 'websites', 'scopeCode' => $this->website->getCode()]);
    expect($api['status'])->toBe(200)
        ->and(configParityStored(CONFIG_PARITY_STREET, 'websites', $websiteId))->toBeFalse()
        ->and(configParityStreetLines($websiteId))->toBeNull();

    $service->save(CONFIG_PARITY_STREET, '3', 'websites', $websiteId);
    $configId = (int) Mage::getResourceModel('core/config_data_collection')
        ->addFieldToFilter('path', CONFIG_PARITY_STREET)
        ->addFieldToFilter('scope', 'websites')
        ->addFieldToFilter('scope_id', $websiteId)
        ->getFirstItem()
        ->getId();
    $cli = configParityCli(new ConfigDelete(), ['config_id' => $configId, '--force' => true]);
    expect($cli['code'])->toBe(Command::SUCCESS)
        ->and(configParityStored(CONFIG_PARITY_STREET, 'websites', $websiteId))->toBeFalse()
        ->and(configParityStreetLines($websiteId))->toBeNull();
});

it('refuses a scope that does not exist on the CLI', function (): void {
    $cli = configParityCli(new ConfigSet(), ['path' => CONFIG_PARITY_NAME, 'value' => 'x', '--scope' => 'websites', '--scope-id' => 999999]);

    expect($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain('The website with the ID 999999 does not exist.');
});

describe('a path that no field declares', function (): void {
    it('accepts a field of a group that the admin makes from the module list, on the API and the CLI', function (): void {
        $path = 'advanced/modules_disable_output/Mage_Cms';
        $cli = configParityCli(new ConfigSet(), ['path' => $path, 'value' => '0', '--scope' => 'default', '--scope-id' => 0]);
        $api = configParityApi('PUT', $path, ['value' => '0']);
        $stored = configParityStored($path, 'default', 0);
        Mage::getConfig()->deleteConfig($path);

        expect($cli['code'])->toBe(Command::SUCCESS)
            ->and($api['status'])->toBe(200)
            ->and($stored)->toBe('0');
    });

    it('is refused on the API and the CLI, as in the admin, and saved on the CLI with --undeclared', function (): void {
        $path = 'dev/parity/undeclared';
        $api = configParityApi('PUT', $path, ['value' => 'y']);
        $cli = configParityCli(new ConfigSet(), ['path' => $path, 'value' => 'x', '--scope' => 'default', '--scope-id' => 0]);
        $stored = configParityStored($path, 'default', 0);
        $forced = configParityCli(new ConfigSet(), ['path' => $path, 'value' => 'x', '--scope' => 'default', '--scope-id' => 0, '--undeclared' => true]);
        $forcedStored = configParityStored($path, 'default', 0);
        Mage::getConfig()->deleteConfig($path);

        expect($api['status'])->toBe(404)
            ->and($cli['code'])->toBe(Command::FAILURE)
            ->and($cli['output'])->toContain("Configuration setting '{$path}' not found")
            ->and($stored)->toBeFalse()
            ->and($forced['code'])->toBe(Command::SUCCESS)
            ->and($forcedStored)->toBe('x');
    });
});
