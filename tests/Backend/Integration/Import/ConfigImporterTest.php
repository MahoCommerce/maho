<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Config;
use Maho\Import\RowException;

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
    }
    $config->deleteConfig('catalog/frontend/imp_swatch_ids', 'default', 0);
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
    ]);

    $result = (new Config())->import($path);
    expect($result->updated)->toBe(4);

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
