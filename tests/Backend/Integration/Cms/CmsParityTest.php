<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Cms\Api\CmsBlock;
use Mage\Cms\Api\CmsBlockProcessor;
use Mage\Cms\Api\CmsPage;
use Mage\Cms\Api\CmsPageProcessor;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin pages, the API and the CMS importer save and delete the CMS pages and blocks through the services of
 * Mage_Cms, and the media library finds and deletes its files through Mage_Cms_Service_Media (#1348).
 * Each test gives the same input to the transports and compares the outcome.
 */

const CMS_PARITY_PREFIX = 'parity-cms-';

/**
 * @param array<string, mixed> $properties
 */
function cmsParityApi(string $processorClass, string $resourceClass, array $properties, ?int $deleteId = null): array
{
    $dto = new $resourceClass();
    foreach ($properties as $property => $value) {
        $dto->{$property} = $value;
    }
    $request = AdminApiParity::jsonRequest($deleteId === null ? 'POST' : 'DELETE', '/api/rest/v2/cms');
    return AdminApiParity::api($request, fn() => new $processorClass(AdminApiParity::adminSecurity())->process(
        $dto,
        $deleteId === null ? new Post(class: $resourceClass) : new Delete(class: $resourceClass),
        $deleteId === null ? [] : ['id' => $deleteId],
        ['request' => $request],
    ));
}

function cmsParityCleanup(): void
{
    Mage::getResourceModel('cms/page_collection')->addFieldToFilter('identifier', ['like' => CMS_PARITY_PREFIX . '%'])->walk('delete');
    Mage::getResourceModel('cms/block_collection')->addFieldToFilter('identifier', ['like' => CMS_PARITY_PREFIX . '%'])->walk('delete');
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    cmsParityCleanup();
});

afterEach(function (): void {
    cmsParityCleanup();
});

describe('pages', function (): void {
    it('refuses a layout update that names a template path on both transports', function (): void {
        $xml = '<reference name="content"><block type="core/template" template="../../../../app/etc/local.xml"/></reference>';
        $validator = Mage::getModel('adminhtml/layoutUpdate_validator');
        $validator->isValid($xml);
        $messages = array_values(array_map(strval(...), $validator->getMessages()));

        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', [
            'identifier' => CMS_PARITY_PREFIX . 'admin', 'title' => 'Parity', 'stores' => [0], 'is_active' => 1,
            'root_template' => 'one_column', 'layout_update_xml' => $xml,
        ]);
        $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, [
            'identifier' => CMS_PARITY_PREFIX . 'api', 'title' => 'Parity', 'stores' => [0], 'layoutUpdateXml' => $xml,
        ]);

        expect($messages)->not->toBe([])
            ->and($admin['errors'])->toBe($messages)
            ->and($api)->toMatchArray(['status' => 422, 'errors' => $messages])
            ->and($api['fields'])->toContain('layoutUpdateXml')
            ->and(Mage::getResourceModel('cms/page_collection')->addFieldToFilter('identifier', ['like' => CMS_PARITY_PREFIX . '%'])->getSize())->toBe(0);
    });

    it('refuses an unknown layout code on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', [
            'identifier' => CMS_PARITY_PREFIX . 'admin', 'title' => 'Parity', 'stores' => [0], 'is_active' => 1, 'root_template' => '1column',
        ]);
        $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, [
            'identifier' => CMS_PARITY_PREFIX . 'api', 'title' => 'Parity', 'stores' => [0], 'pageLayout' => '1column',
        ]);

        expect($admin['errors'])->toHaveCount(1)
            ->and($admin['errors'][0])->toContain('one_column')
            ->and($api)->toMatchArray(['status' => 422, 'errors' => $admin['errors']]);
    });

    it('refuses an identifier that a page of the same store view has on both transports', function (): void {
        Mage::getService('cms/page')->save(Mage::getModel('cms/page')->setIdentifier(CMS_PARITY_PREFIX . 'taken')
            ->setTitle('Taken')->setStores([0])->setRootTemplate('one_column'));
        $message = 'A page URL key for specified store already exists.';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', [
            'identifier' => CMS_PARITY_PREFIX . 'taken', 'title' => 'Parity', 'stores' => [0], 'is_active' => 1, 'root_template' => 'one_column',
        ]);
        $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, [
            'identifier' => CMS_PARITY_PREFIX . 'taken', 'title' => 'Parity', 'stores' => [0],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });

    it('makes the identifier from the title when the request has none, as the admin page does', function (): void {
        $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, ['title' => CMS_PARITY_PREFIX . 'From Title', 'stores' => [0]]);

        expect($api['status'])->toBe(200)
            ->and($api['result']->identifier)->toBe(CMS_PARITY_PREFIX . 'from-title');
    });

    it('refuses to delete an unknown page on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'delete', ['page_id' => 999999]);
        $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, [], 999999);

        expect($admin['errors'])->toBe(['This page no longer exists.'])
            ->and($api)->toMatchArray(['status' => 404, 'errors' => ['This page no longer exists.']]);
    });

    it('refuses an identifier of the importer with the rule of the page', function (): void {
        expect(Mage::getResourceSingleton('cms/page')->getIdentifierError('About Us'))
            ->toBe('The page URL key contains capital letters or disallowed symbols.')
            ->and(Mage::getResourceSingleton('cms/page')->getIdentifierError('2026'))
            ->toBe('The page URL key cannot consist only of numbers.')
            ->and(Mage::getResourceSingleton('cms/page')->getIdentifierError('about-us.html'))->toBeNull();
    });
});

describe('blocks', function (): void {
    it('refuses a block without an identifier on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_BlockController::class, 'save', [
            'identifier' => '', 'title' => 'Parity', 'stores' => [0], 'is_active' => 1,
        ]);
        $api = cmsParityApi(CmsBlockProcessor::class, CmsBlock::class, ['identifier' => '', 'title' => 'Parity', 'stores' => [0]]);

        expect($admin['errors'])->toBe(['Identifier is required.'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['Identifier is required.'], 'fields' => ['identifier']]);
    });

    it('refuses an identifier that a block of the same store view has on both transports', function (): void {
        Mage::getService('cms/block')->save(Mage::getModel('cms/block')->setIdentifier(CMS_PARITY_PREFIX . 'taken')
            ->setTitle('Taken')->setData('stores', [0]));
        $message = 'A block identifier with the same properties already exists in the selected store.';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_BlockController::class, 'save', [
            'identifier' => CMS_PARITY_PREFIX . 'taken', 'title' => 'Parity', 'stores' => [0], 'is_active' => 1,
        ]);
        $api = cmsParityApi(CmsBlockProcessor::class, CmsBlock::class, [
            'identifier' => CMS_PARITY_PREFIX . 'taken', 'title' => 'Parity', 'stores' => [0],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });
});

describe('media', function (): void {
    it('finds no file outside the folder', function (string $name): void {
        $root = Mage::helper('cms/wysiwyg_images')->getStorageRootPath();

        expect(fn() => Mage::getService('cms/media')->getFile($root, $name))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class, 'File not found.');
    })->with(['../index.php', 'sub/../../index.php', '', 'no-such-file.jpg']);

    it('keeps a path of the API inside the root of the library', function (): void {
        expect(Mage::getService('cms/media')->getFolder('../../app/etc'))
            ->toStartWith(Mage::helper('cms/wysiwyg_images')->getStorageRootPath());
    });
});
