<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Cms\Api\CmsBlock;
use Mage\Cms\Api\CmsBlockProcessor;
use Mage\Cms\Api\CmsPage;
use Mage\Cms\Api\CmsPageProcessor;
use Mage\Cms\Api\Media;
use Mage\Cms\Api\MediaProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\RequestStack;
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

/**
 * @param array<string, mixed> $body
 */
function cmsParityApiUpdate(int $id, array $body): array
{
    $dto = new CmsPage();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $request = AdminApiParity::jsonRequest('PUT', "/api/rest/v2/cms-pages/{$id}", $body);
    return AdminApiParity::api($request, fn() => new CmsPageProcessor(AdminApiParity::adminSecurity())
        ->process($dto, new Put(class: CmsPage::class), ['id' => $id], ['request' => $request]));
}

/**
 * Run $call with the value $value for the setting $path in the default scope, then put the stored value back.
 */
function cmsParityWithConfig(string $path, string $value, callable $call): void
{
    $stored = Mage::getResourceModel('core/config_data_collection')->addFieldToFilter('path', $path)
        ->addFieldToFilter('scope', 'default')->addFieldToFilter('scope_id', 0)->getFirstItem();
    $config = Mage::getModel('core/config');
    $config->saveConfig($path, $value, 'default', 0);
    try {
        $call();
    } finally {
        $stored->getId() ? $config->saveConfig($path, (string) $stored->getValue(), 'default', 0) : $config->deleteConfig($path, 'default', 0);
    }
}

/**
 * Only a file that PHP received through a form passes is_uploaded_file(), so this uploader writes the temp file of the test.
 */
class CmsParityUploader extends Mage_Core_Model_File_Uploader
{
    #[\Override]
    protected function _storeFile(\Maho\Storage\Mount $mount, string $path): bool
    {
        $mount->copyFromLocalFile($this->_file['tmp_name'], $path);
        unlink($this->_file['tmp_name']);
        return true;
    }
}

/**
 * Use $className for the model alias core/file_uploader, or the default class when $className is empty.
 */
function cmsParityUploaderClass(string $className): void
{
    Mage::getConfig()->setNode('global/models/core/rewrite/file_uploader', $className);
    (function (): void {
        unset($this->_classNameCache['global/models']['core']['file_uploader']);
    })->call(Mage::getConfig());
}

/**
 * Put a PNG image of 3x2 pixels named $name in the upload field $field.
 */
function cmsParityUploadField(string $field, string $name): void
{
    $tmp = (string) tempnam(sys_get_temp_dir(), 'cms');
    imagepng(imagecreatetruecolor(3, 2), $tmp);
    $_FILES[$field] = ['name' => $name, 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($tmp)];
}

/**
 * The extension that Mage_Cms_Service_Media::upload() gives a file, with its dot.
 */
function cmsParityImageExtension(): string
{
    return '.' . image_type_to_extension(Mage::getStoreConfigAsInt('system/media_storage_configuration/image_file_type') ?: IMAGETYPE_WEBP, false);
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

    it('gives a new page the default layout of the theme in the admin, the API and the importer', function (): void {
        Mage::unregister('_singleton/page/source_layout');
        Mage::register('_singleton/page/source_layout', new class extends Mage_Page_Model_Source_Layout {
            #[\Override]
            public function getDefaultValue()
            {
                return 'two_columns_left';
            }
        });
        $csv = tempnam(sys_get_temp_dir(), 'cms') . '.csv';
        file_put_contents($csv, "identifier,stores,title,content\n" . CMS_PARITY_PREFIX . "import,,Parity,<p>Body</p>\n");

        try {
            $admin = Mage::getService('cms/page')->newPage();
            $api = cmsParityApi(CmsPageProcessor::class, CmsPage::class, ['identifier' => CMS_PARITY_PREFIX . 'api', 'title' => 'Parity']);
            (new Maho\Import\Importer\CmsPages())->import($csv);
            $imported = Mage::getModel('cms/page')->load(CMS_PARITY_PREFIX . 'import', 'identifier');
        } finally {
            Mage::unregister('_singleton/page/source_layout');
            unlink($csv);
        }

        expect($admin->getRootTemplate())->toBe('two_columns_left')
            ->and($admin->getIsActive())->toBeTrue()
            ->and($api['result']->pageLayout)->toBe('two_columns_left')
            ->and($api['result']->isActive)->toBeTrue()
            ->and($imported->getRootTemplate())->toBe('two_columns_left');
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

describe('a page that the configuration uses', function (): void {
    it('refuses to disable it with a conflict on both transports, and keeps it enabled', function (string $path): void {
        $page = Mage::getService('cms/page')->save(Mage::getModel('cms/page')->setIdentifier(CMS_PARITY_PREFIX . 'used')
            ->setTitle('Parity')->setStores([0])->setRootTemplate('one_column')->setIsActive());
        $message = sprintf('Cannot disable page, it is used in configuration "%s".', $path);

        cmsParityWithConfig($path, CMS_PARITY_PREFIX . 'used', function () use ($page, $message): void {
            $admin = AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', [
                'page_id' => $page->getId(), 'identifier' => CMS_PARITY_PREFIX . 'used', 'title' => 'Parity', 'stores' => [0],
                'is_active' => 0, 'root_template' => 'one_column',
            ]);
            $api = cmsParityApiUpdate((int) $page->getId(), ['isActive' => false]);

            expect($admin['errors'])->toBe([$message])
                ->and($admin['success'])->toBe([])
                ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]])
                ->and(Mage::getModel('cms/page')->load($page->getId())->getIsActive())->toBeTrue();
        });
    })->with([
        'home page' => [Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE],
        'no-route page' => [Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE],
        'no-cookies page' => [Mage_Cms_Helper_Page::XML_PATH_NO_COOKIES_PAGE],
    ]);

    it('saves a page that no setting uses as disabled', function (): void {
        $page = Mage::getService('cms/page')->save(Mage::getModel('cms/page')->setIdentifier(CMS_PARITY_PREFIX . 'unused')
            ->setTitle('Parity')->setStores([0])->setRootTemplate('one_column')->setIsActive());

        $api = cmsParityApiUpdate((int) $page->getId(), ['isActive' => false]);

        expect($api['status'])->toBe(200)
            ->and(Mage::getModel('cms/page')->load($page->getId())->getIsActive())->toBeFalse();
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

describe('media upload', function (): void {
    beforeEach(function (): void {
        cmsParityUploaderClass(CmsParityUploader::class);
        $this->root = Mage::helper('cms/wysiwyg_images')->getStorageRootPath();
    });

    afterEach(function (): void {
        foreach (['cms-parity-admin', 'cms-parity-api'] as $name) {
            try {
                Mage::getService('cms/media')->delete($this->root, $name . cmsParityImageExtension());
            } catch (Mage_Core_Exception_NoSuchEntity) {
                // The test refused the upload
            }
        }
        cmsParityUploaderClass('');
        $_FILES = [];
    });

    it('converts the upload to the configured image type on the admin page and in the API', function (): void {
        cmsParityUploadField('image', 'cms-parity-admin.png');
        cmsParityUploadField('file', 'cms-parity-api.png');
        $extension = cmsParityImageExtension();

        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/cms_wysiwyg_images/upload', 'POST'));
        Mage::app()->setRequest($request);
        $response = new Mage_Core_Controller_Response_Http();
        new Mage_Adminhtml_Cms_Wysiwyg_ImagesController($request, $response)->uploadAction();
        $admin = json_decode((string) $response->getBody(), true);

        $requestStack = new RequestStack();
        $requestStack->push(SymfonyRequest::create('/api/rest/v2/media', 'POST', ['folder' => $this->root]));
        $api = new MediaProcessor(AdminApiParity::adminSecurity(), $requestStack)->process(new Media(), new Post(class: Media::class));

        expect($admin)->toBe([
            'path' => $this->root . '/cms-parity-admin' . $extension, 'file' => 'cms-parity-admin' . $extension,
            'size' => $admin['size'], 'width' => 3, 'height' => 2,
        ])
            ->and($api->path)->toBe($this->root . '/cms-parity-api' . $extension)
            ->and($api->dimensions)->toBe(['width' => 3, 'height' => 2])
            ->and(Mage::getStorage('media')->fileExists($admin['path']))->toBeTrue();
    });

    it('refuses a file that is not an image on the admin page and in the API', function (): void {
        foreach (['image' => 'cms-parity-admin.png', 'file' => 'cms-parity-api.png'] as $field => $name) {
            $tmp = (string) tempnam(sys_get_temp_dir(), 'cms');
            file_put_contents($tmp, 'not an image');
            $_FILES[$field] = ['name' => $name, 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 12];
        }

        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/cms_wysiwyg_images/upload', 'POST'));
        Mage::app()->setRequest($request);
        $response = new Mage_Core_Controller_Response_Http();
        new Mage_Adminhtml_Cms_Wysiwyg_ImagesController($request, $response)->uploadAction();
        $admin = json_decode((string) $response->getBody(), true);

        $requestStack = new RequestStack();
        $requestStack->push(SymfonyRequest::create('/api/rest/v2/media', 'POST', ['folder' => $this->root]));
        $api = AdminApiParity::api($requestStack->getCurrentRequest(), fn() => new MediaProcessor(AdminApiParity::adminSecurity(), $requestStack)
            ->process(new Media(), new Post(class: Media::class)));

        expect($admin['error'])->toBeTrue()
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe([$admin['message']]);
    });
});
