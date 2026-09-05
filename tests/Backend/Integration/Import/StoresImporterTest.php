<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Stores;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function storesCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stores') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function storesCleanup(): void
{
    foreach (['imp_alpha', 'imp_beta', 'imp_alpha_old'] as $code) {
        deletePriceWebsite($code);
    }
    foreach (['Import Alpha Root', 'Import Beta Root'] as $name) {
        $category = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('level', 1)
            ->addAttributeToFilter('name', $name)
            ->getFirstItem();
        if ($category->getId()) {
            Mage::getModel('catalog/category')->load($category->getId())->delete();
        }
    }
}

beforeEach(fn() => storesCleanup());
afterEach(fn() => storesCleanup());

it('creates website, group, root category and store, and reruns without duplicates', function (): void {
    $header = ['website_code', 'website_name', 'group_name', 'root_category', 'store_code', 'store_name', 'store_sort_order'];
    $path = storesCsv([
        $header,
        ['imp_alpha', 'Import Alpha', 'Alpha Group', 'Import Alpha Root', 'imp_alpha', 'Alpha Store', '91'],
        ['imp_beta', 'Import Beta', '', 'Import Beta Root', 'imp_beta', '', '92'],
    ]);

    $result = (new Stores())->import($path);
    expect($result->created)->toBe(6);

    $website = Mage::getModel('core/website')->load('imp_alpha', 'code');
    expect($website->getId())->not->toBeEmpty();
    $group = $website->getDefaultGroup();
    expect($group->getName())->toBe('Alpha Group');
    $root = Mage::getModel('catalog/category')->load($group->getRootCategoryId());
    expect($root->getName())->toBe('Import Alpha Root');
    expect((int) $root->getLevel())->toBe(1);
    $store = Mage::app()->getStore('imp_alpha');
    expect($store->getName())->toBe('Alpha Store');
    expect((int) $group->getDefaultStoreId())->toBe((int) $store->getId());
    expect(Mage::app()->getStore('imp_beta')->getName())->toBe('Imp_beta');
    expect(Mage::getModel('core/website')->load('imp_beta', 'code')->getDefaultGroup()->getName())->toBe('Import Beta');

    $again = (new Stores())->import($path);
    expect($again->created)->toBe(0);
    $codes = Mage::getResourceModel('core/website_collection')->addFieldToFilter('code', ['like' => 'imp%'])->getColumnValues('code');
    sort($codes);
    expect($codes)->toBe(['imp_alpha', 'imp_beta']);
    expect(Mage::getResourceModel('core/store_collection')->addFieldToFilter('code', ['in' => ['imp_alpha', 'imp_beta']])->count())->toBe(2);
    unlink($path);
});

it('renames a website and a store through the previous code columns, once', function (): void {
    createPriceWebsite('imp_alpha_old', 93);
    $path = storesCsv([
        ['website_code', 'website_previous_code', 'root_category', 'store_code', 'store_previous_code'],
        ['imp_alpha', 'imp_alpha_old', 'Import Alpha Root', 'imp_alpha', 'imp_alpha_old'],
    ]);

    (new Stores())->import($path);
    expect(Mage::getModel('core/website')->load('imp_alpha_old', 'code')->getId())->toBeEmpty();
    expect(Mage::app()->getStore('imp_alpha')->getWebsite()->getCode())->toBe('imp_alpha');

    (new Stores())->import($path);
    expect(Mage::getResourceModel('core/website_collection')->addFieldToFilter('code', ['like' => 'imp_alpha%'])->count())->toBe(1);
    unlink($path);
});

it('rejects bad codes, duplicates and more than one default website before writing', function (): void {
    $header = ['website_code', 'root_category', 'store_code', 'website_is_default'];
    $importer = new Stores();

    $path = storesCsv([$header, ['Imp-Alpha', 'Import Alpha Root', 'imp_alpha', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2: website_code');
    unlink($path);

    $path = storesCsv([$header, ['imp_alpha', 'Import Alpha Root', 'admin', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'cannot be admin');
    unlink($path);

    $path = storesCsv([$header, ['imp_alpha', 'Import Alpha Root', 'imp_alpha', '1'], ['imp_beta', 'Import Beta Root', 'imp_beta', '1']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'more than one row');
    unlink($path);

    expect(Mage::getModel('core/website')->load('imp_alpha', 'code')->getId())->toBeEmpty();
});
