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
