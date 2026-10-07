<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Config;
use Maho\Import\RowException;
use MahoCLI\Commands\ConfigSet;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function configCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'config') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function configCleanup(): void
{
    $config = Mage::getModel('core/config');
    $config->deleteConfig('general/store_information/name', 'default', 0);
    $website = Mage::getModel('core/website')->load('imp_cfg', 'code');
    if ($website->getId()) {
        $config->deleteConfig('general/store_information/name', 'websites', (int) $website->getId());
        $config->deleteConfig('design/theme/default', 'stores', (int) Mage::app()->getStore('imp_cfg')->getId());
        $config->deleteConfig('web/unsecure/base_link_url', 'stores', (int) Mage::app()->getStore('imp_cfg')->getId());
    }
    $config->deleteConfig('catalog/frontend/imp_swatch_ids', 'default', 0);
    $config->deleteConfig('catalog/frontend/imp_store_url', 'default', 0);
    deletePriceWebsite('imp_cfg');
    Mage::app()->getCache()->cleanType('config');
}

beforeEach(fn() => configCleanup());
afterEach(fn() => configCleanup());

it('saves values by scope code and resolves macros', function (): void {
    createPriceWebsite('imp_cfg', 94);
    $path = configCsv([
        ['path', 'value', 'scope', 'scope_code'],
        ['general/store_information/name', 'Import Default', '', ''],
        ['general/store_information/name', 'Import Website', 'websites', 'imp_cfg'],
        ['design/theme/default', 'imp_cfg', 'stores', 'imp_cfg'],
        ['catalog/frontend/imp_swatch_ids', '{{attribute_ids:color,name}}|{{store_id:imp_cfg}}', 'default', ''],
        ['catalog/frontend/imp_store_url', '{{store_url:imp_cfg}}', 'default', ''],
    ]);

    $result = (new Config())->import($path);
    expect($result->updated)->toBe(5);

    $websiteId = (int) Mage::getModel('core/website')->load('imp_cfg', 'code')->getId();
    $storeId = (int) Mage::app()->getStore('imp_cfg')->getId();
    $read = fn(string $p, string $scope, int $id) => Mage::getResourceModel('core/config')->getReadConnection()->fetchOne(
        Mage::getResourceModel('core/config')->getReadConnection()->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core_config_data'), 'value')
            ->where('path = ?', $p)->where('scope = ?', $scope)->where('scope_id = ?', $id),
    );
    expect($read('general/store_information/name', 'default', 0))->toBe('Import Default');
    expect($read('general/store_information/name', 'websites', $websiteId))->toBe('Import Website');
    expect($read('design/theme/default', 'stores', $storeId))->toBe('imp_cfg');
    $eav = Mage::getSingleton('eav/config');
    $expected = $eav->getAttribute('catalog_product', 'color')->getId() . ',' . $eav->getAttribute('catalog_product', 'name')->getId() . '|' . $storeId;
    expect($read('catalog/frontend/imp_swatch_ids', 'default', 0))->toBe($expected);
    expect($read('catalog/frontend/imp_store_url', 'default', 0))->toBe(Mage::app()->getStore('imp_cfg')->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK));

    (new Config())->import($path);
    $count = Mage::getResourceModel('core/config')->getReadConnection()->fetchOne(
        Mage::getResourceModel('core/config')->getReadConnection()->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core_config_data'), 'COUNT(*)')
            ->where('path = ?', 'general/store_information/name')
            ->where('value LIKE ?', 'Import %'),
    );
    expect((int) $count)->toBe(2);
    unlink($path);
});

it('resolves a store url macro against the web rows of the same file', function (): void {
    createPriceWebsite('imp_cfg', 94);
    $path = configCsv([
        ['path', 'value', 'scope', 'scope_code'],
        ['catalog/frontend/imp_store_url', '{{store_url:imp_cfg}}', 'default', ''],
        ['web/unsecure/base_link_url', 'https://imp-cfg.example/shop/', 'stores', 'imp_cfg'],
    ]);

    (new Config())->import($path);
    unlink($path);

    $value = Mage::getResourceModel('core/config')->getReadConnection()->fetchOne(
        Mage::getResourceModel('core/config')->getReadConnection()->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core_config_data'), 'value')
            ->where('path = ?', 'catalog/frontend/imp_store_url')->where('scope = ?', 'default')->where('scope_id = ?', 0),
    );
    expect($value)->toStartWith('https://imp-cfg.example/shop/');
});

/**
 * @return array<string, int> is_visible of the middlename attribute, by entity type code
 */
function middlenameVisibility(): array
{
    $resource = Mage::getSingleton('core/resource');
    $read = $resource->getConnection('core_read');
    return array_map('intval', $read->fetchPairs(
        $read->select()
            ->from(['cea' => $resource->getTableName('customer/eav_attribute')], [])
            ->join(['ea' => $resource->getTableName('eav/attribute')], 'ea.attribute_id = cea.attribute_id', [])
            ->join(['et' => $resource->getTableName('eav/entity_type')], 'et.entity_type_id = ea.entity_type_id', ['entity_type_code'])
            ->columns(['is_visible' => 'cea.is_visible'])
            ->where('ea.attribute_code = ?', 'middlename'),
    ));
}

/**
 * @param array<string, int> $visibility
 */
function setMiddlenameVisibility(array $visibility): void
{
    $eav = Mage::getSingleton('eav/config');
    foreach ($visibility as $entityType => $isVisible) {
        Mage::getSingleton('core/resource')->getConnection('core_write')->update(
            Mage::getSingleton('core/resource')->getTableName('customer/eav_attribute'),
            ['is_visible' => $isVisible],
            ['attribute_id = ?' => (int) $eav->getAttribute($entityType, 'middlename')->getId()],
        );
    }
    $eav->clear();
}

it('saves a value through the backend model of its field', function (Closure $save): void {
    $config = Mage::getModel('core/config');
    $oldValue = Mage::getStoreConfig('customer/address/middlename_show', 0);
    $oldVisibility = middlenameVisibility();
    setMiddlenameVisibility(['customer' => 1, 'customer_address' => 1]);

    try {
        $save('customer/address/middlename_show', '0');
        expect(middlenameVisibility())->toBe(['customer' => 0, 'customer_address' => 0]);
    } finally {
        $config->saveConfig('customer/address/middlename_show', $oldValue, 'default', 0);
        setMiddlenameVisibility($oldVisibility);
        Mage::app()->getCache()->cleanType('config');
    }
})->with([
    'import:config' => [function (string $configPath, string $value): void {
        $path = configCsv([['path', 'value'], [$configPath, $value]]);
        (new Config())->import($path);
        unlink($path);
    }],
    'config:set' => [function (string $configPath, string $value): void {
        $command = new ConfigSet('config:set');
        (new Application())->addCommand($command);
        $result = (new CommandTester($command))->execute(['path' => $configPath, 'value' => $value, '--scope' => 'default', '--scope-id' => 0]);
        expect($result)->toBe(Command::SUCCESS);
    }],
]);

it('saves the value of a field whose backend model reads only an upload', function (): void {
    $path = configCsv([['path', 'value'], ['sales/identity/logo', 'default/imp-logo.png']]);

    try {
        (new Config())->import($path);
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $value = $read->fetchOne(
            $read->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('core_config_data'), 'value')
                ->where('path = ?', 'sales/identity/logo')->where('scope = ?', 'default')->where('scope_id = ?', 0),
        );
        expect($value)->toBe('default/imp-logo.png');
    } finally {
        unlink($path);
        Mage::getModel('core/config')->deleteConfig('sales/identity/logo', 'default', 0);
        Mage::app()->getCache()->cleanType('config');
    }
});

it('rejects unknown scopes, codes and macros before writing', function (): void {
    $importer = new Config();

    $path = configCsv([['path', 'value', 'scope', 'scope_code'], ['general/store_information/name', 'x', 'website', 'base']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "line 2: scope 'website'");
    unlink($path);

    $path = configCsv([['path', 'value', 'scope', 'scope_code'], ['general/store_information/name', 'x', 'stores', 'no_such_store']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "unknown store code 'no_such_store'");
    unlink($path);

    $path = configCsv([['path', 'value'], ['general/store_information/name', '{{attribute_id:no_such_attribute}}']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "unknown attribute code 'no_such_attribute'");
    unlink($path);

    $path = configCsv([['path', 'value'], ['badpath', 'x']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "path 'badpath'");
    unlink($path);
});
