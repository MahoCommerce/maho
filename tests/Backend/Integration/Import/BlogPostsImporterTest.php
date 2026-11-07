<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\BlogPosts;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function blogCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'blog') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function blogCleanup(): void
{
    if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        return;
    }
    foreach (Mage::getModel('blog/post')->getCollection()->addAttributeToFilter('url_key', ['like' => 'imp-%']) as $post) {
        $post->delete();
    }
}

beforeEach(function (): void {
    if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
        test()->markTestSkipped('Maho_Blog is disabled');
    }
    blogCleanup();
});
afterEach(fn() => blogCleanup());

it('creates a post from a content file and updates it on rerun', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $contentDir = sys_get_temp_dir() . '/imp-blog-' . uniqid();
    mkdir($contentDir);
    file_put_contents($contentDir . '/post.html', '<p>Post body</p>');
    $options = [AbstractCmsImporter::OPTION_CONTENT_DIR => $contentDir];
    $path = blogCsv([
        ['url_key', 'stores', 'title', 'publish_date', 'content_file', 'image', 'meta_title'],
        ['imp-post', $store, 'Imp Post', '2026-02-03', 'post.html', 'imp/pic.webp', 'Imp Meta'],
    ]);

    expect((new BlogPosts())->import($path, $options)->created)->toBe(1);
    $post = Mage::getModel('blog/post')->load(Mage::getModel('blog/post')->getPostIdByUrlKey('imp-post', 1));
    expect($post->getTitle())->toBe('Imp Post');
    expect($post->getContent())->toBe('<p>Post body</p>');
    expect($post->getImage())->toBe('imp/pic.webp');
    expect($post->getMetaTitle())->toBe('Imp Meta');
    expect(substr((string) $post->getPublishDate(), 0, 10))->toBe('2026-02-03');

    file_put_contents($contentDir . '/post.html', '<p>New body</p>');
    $again = (new BlogPosts())->import($path, $options);
    expect($again->created)->toBe(0)->and($again->updated)->toBe(1);
    expect(Mage::getModel('blog/post')->getCollection()->addAttributeToFilter('url_key', 'imp-post')->count())->toBe(1);
    expect(Mage::getModel('blog/post')->load($post->getId())->getContent())->toBe('<p>New body</p>');

    unlink($path);
    unlink($contentDir . '/post.html');
    rmdir($contentDir);
});

it('rejects a bad url key, a missing store list and a bad date', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $importer = new BlogPosts();
    $header = ['url_key', 'stores', 'title', 'publish_date', 'content'];

    $path = blogCsv([$header, ['Imp Post', $store, 'T', '', 'x']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'line 2: url_key');
    unlink($path);

    $path = blogCsv([$header, ['imp-post', '', 'T', '', 'x']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'stores');
    unlink($path);

    $path = blogCsv([$header, ['imp-post', $store, 'T', 'someday', 'x']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'publish_date');
    unlink($path);
});

it('imports a user-defined attribute by option label and the attribute set by name', function (): void {
    $type = Mage::getSingleton('eav/config')->getEntityType(Maho_Blog_Model_Post::ENTITY);
    $code = 'imp_level_' . substr(uniqid(), -6);
    $setId = (int) $type->getDefaultAttributeSetId();
    $attribute = Mage::getModel('blog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_set_id' => $setId,
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->getFirstItem()
            ->getId(),
        'attribute_code' => $code,
        'backend_type' => 'int',
        'frontend_input' => 'select',
        'source_model' => 'eav/entity_attribute_source_table',
        'frontend_label' => 'Level',
        'is_user_defined' => 1,
        'is_global' => 1,
        'option' => ['value' => ['o1' => ['Beginner'], 'o2' => ['Expert']], 'order' => ['o1' => 1, 'o2' => 2]],
    ]);
    $attribute->save();
    Mage::getSingleton('eav/config')->clear();

    $store = Mage::app()->getStore(1)->getCode();
    $contentDir = sys_get_temp_dir() . '/imp-blog-' . uniqid();
    mkdir($contentDir);
    file_put_contents($contentDir . '/post.html', '<p>Body</p>');
    $options = [AbstractCmsImporter::OPTION_CONTENT_DIR => $contentDir];
    $path = blogCsv([
        ['url_key', 'stores', 'title', 'content_file', 'attribute_set', $code],
        ['imp-level', $store, 'Imp Level', 'post.html', 'Default', 'Expert'],
    ]);
    $badPath = blogCsv([
        ['url_key', 'stores', 'title', 'content_file', $code],
        ['imp-level-bad', $store, 'Imp Bad', 'post.html', 'Master'],
    ]);

    try {
        expect((new BlogPosts())->import($path, $options)->created)->toBe(1);
        $post = Mage::getModel('blog/post')->load(Mage::getModel('blog/post')->getPostIdByUrlKey('imp-level', 1));
        expect($attribute->getSource()->getOptionText($post->getData($code)))->toBe('Expert')
            ->and($post->getAttributeSetId())->toBe((int) $type->getDefaultAttributeSetId())
            ->and(fn() => (new BlogPosts())->validate($badPath, $options))->toThrow(RowException::class);
    } finally {
        $attribute->delete();
        Mage::getSingleton('eav/config')->clear();
        unlink($path);
        unlink($badPath);
        unlink($contentDir . '/post.html');
        rmdir($contentDir);
    }
});
