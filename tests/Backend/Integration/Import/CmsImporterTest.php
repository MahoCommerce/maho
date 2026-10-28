<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\CmsBlocks;
use Maho\Import\Importer\CmsPages;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function cmsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'cms') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function cmsCleanup(): void
{
    foreach (Mage::getModel('cms/page')->getCollection()->addFieldToFilter('identifier', ['like' => 'imp-%']) as $page) {
        $page->delete();
    }
    foreach (Mage::getModel('cms/block')->getCollection()->addFieldToFilter('identifier', ['like' => 'imp-%']) as $block) {
        $block->delete();
    }
    Mage::getModel('core/config')->deleteConfig('web/default/cms_home_page', 'stores', 1);
    Mage::app()->getCache()->cleanType('config');
}

beforeEach(fn() => cmsCleanup());
afterEach(fn() => cmsCleanup());

it('creates pages and blocks with bodies from files, sets the home page and reruns without duplicates', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $contentDir = sys_get_temp_dir() . '/imp-cms-' . uniqid();
    mkdir($contentDir);
    file_put_contents($contentDir . '/block.html', '<p>Block body</p>');
    file_put_contents($contentDir . '/home.html', '<h1>Home body</h1>');
    $options = [AbstractCmsImporter::OPTION_CONTENT_DIR => $contentDir];

    $blocks = cmsCsv([
        ['identifier', 'stores', 'title', 'content_file'],
        ['imp-block', '', 'Imp Block', 'block.html'],
    ]);
    $pages = cmsCsv([
        ['identifier', 'stores', 'title', 'content_file', 'content', 'is_home', 'root_template'],
        ['imp-home', $store, 'Imp Home', 'home.html', '', '1', 'one_column'],
        ['imp-inline', '', 'Imp Inline', '', '<p>Inline {{cms_block_id:imp-block}} {{store_id:' . $store . '}}</p>', '0', ''],
    ]);

    expect((new CmsBlocks())->import($blocks, $options)->created)->toBe(1);
    expect((new CmsPages())->import($pages, $options)->created)->toBe(2);

    $block = Mage::getModel('cms/block')->load('imp-block', 'identifier');
    expect($block->getContent())->toBe('<p>Block body</p>');
    expect(array_map('intval', $block->getStores()))->toBe([0]);
    $home = Mage::getModel('cms/page')->setStoreId(1)->load('imp-home', 'identifier');
    expect($home->getContent())->toBe('<h1>Home body</h1>');
    expect($home->getRootTemplate())->toBe('one_column');
    expect(Mage::getModel('cms/page')->load('imp-inline', 'identifier')->getContent())->toBe('<p>Inline ' . $block->getId() . ' 1</p>');
    Mage::app()->getCache()->cleanType('config');
    Mage::app()->reinitStores();
    expect(Mage::getStoreConfig('web/default/cms_home_page', 1))->toBe('imp-home');

    expect((new CmsBlocks())->import($blocks, $options)->updated)->toBe(1);
    expect((new CmsPages())->import($pages, $options)->updated)->toBe(2);
    expect(Mage::getModel('cms/page')->getCollection()->addFieldToFilter('identifier', 'imp-home')->count())->toBe(1);
    expect(Mage::getModel('cms/block')->getCollection()->addFieldToFilter('identifier', 'imp-block')->count())->toBe(1);

    unlink($blocks);
    unlink($pages);
    array_map('unlink', glob($contentDir . '/*'));
    rmdir($contentDir);
});

it('rejects a missing body, an unknown store and a missing content file', function (): void {
    $importer = new CmsPages();
    $header = ['identifier', 'stores', 'title', 'content_file'];

    $path = cmsCsv([$header, ['imp-nobody', '', 'T', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2');
    unlink($path);

    $path = cmsCsv([$header, ['imp-store', 'no_such_store', 'T', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'no_such_store');
    unlink($path);

    $path = cmsCsv([$header, ['imp-file', '', 'T', 'missing.html']]);
    expect(fn() => $importer->validate($path, [AbstractCmsImporter::OPTION_CONTENT_DIR => sys_get_temp_dir()]))->toThrow(RowException::class, 'missing.html');
    unlink($path);

    expect(Mage::getModel('cms/page')->getCollection()->addFieldToFilter('identifier', ['like' => 'imp-%'])->count())->toBe(0);
});

it('imports the page fields, with option labels for a dropdown, and refuses an unknown label', function (): void {
    $typeId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY)->getId();
    $code = 'pest_imp_season_' . substr(uniqid(), -6);
    $attribute = Mage::getModel('cms/attribute')->setData([
        'entity_type_id' => $typeId,
        'attribute_code' => $code,
        'backend_type' => 'static',
        'frontend_input' => 'select',
        'frontend_label' => 'Season',
        'is_user_defined' => 1,
        'option' => ['value' => ['o1' => ['Spring'], 'o2' => ['Summer']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = Mage::getSingleton('core/resource')->getTableName('cms/page');

    try {
        $summer = (string) $attribute->getSource()->getOptionId('Summer');
        $path = cmsCsv([
            ['identifier', 'stores', 'title', 'content', $code],
            ['imp-field', '', 'Imp Field', '<p>Body</p>', 'Summer'],
        ]);
        expect((new CmsPages())->import($path)->created)->toBe(1)
            ->and((string) Mage::getModel('cms/page')->load('imp-field', 'identifier')->getData($code))->toBe($summer);
        unlink($path);

        $path = cmsCsv([
            ['identifier', 'stores', 'title', 'content', $code],
            ['imp-field-bad', '', 'Imp Field Bad', '<p>Body</p>', 'Winter'],
        ]);
        expect(fn() => (new CmsPages())->validate($path))->toThrow(RowException::class, 'Winter');
        unlink($path);
    } finally {
        cmsCleanup();
        $attribute->delete();
        if ($connection->tableColumnExists($table, $code)) {
            $connection->dropColumn($table, $code);
        }
        $connection->resetDdlCache($table);
        Mage::getSingleton('eav/config')->clear();
    }
});
