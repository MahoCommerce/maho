<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

describe('the route that creates a missing resized image', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_controller_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->previousStore = Mage::app()->getStore()->getId();
        $this->previousAction = Mage::app()->getFrontController()->getAction();
        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::app()->setCurrentStore($this->storeId);
        Mage::getSingleton('core/image_config')->reset();

        $this->folder = 'catalog/product/image_controller_' . uniqid();
        $this->settings = Mage::getSingleton('core/image_config')->getActiveSettings('product', $this->storeId);
        $this->pathOf = fn(string $name, int $width): string
            => Mage_Core_Model_Image_Set::buildPath('catalog/product', $this->folder . '/' . $name, $this->settings, $width);

        $this->mount->write($this->folder . '/shirt.png', Maho::getImageManager()->createImage(400, 200)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString());

        $this->create = function (string $path): Mage_Core_Controller_Response_Http {
            $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/media/' . $path));
            $request->setParam('path', $path);
            $response = new Mage_Core_Controller_Response_Http();
            new Mage_Core_ImageController($request, $response)->createAction();
            return $response;
        };
    });

    afterEach(function (): void {
        Mage::getResourceSingleton('core/image_size')->deleteFolder('media', $this->folder);
        Mage::unregister('_singleton/core/image_size');
        Mage::getSingleton('core/image_config')->reset();
        Mage::app()->getFrontController()->setAction($this->previousAction);
        Mage::app()->setCurrentStore($this->previousStore);
        MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('sends the created file of the requested width with a cache life of one year', function (): void {
        $path = ($this->pathOf)('shirt.png', 300);

        $response = ($this->create)($path);
        $headers = $response->getSymfonyResponse()->headers;

        expect($response->getHttpResponseCode())->toBe(200)
            ->and($headers->hasCacheControlDirective('public'))->toBeTrue()
            ->and($headers->getCacheControlDirective('max-age'))->toBe('31536000')
            ->and(getimagesizefromstring((string) $response->getBody())[0])->toBe(300)
            ->and($this->mount->fileExists($path))->toBeTrue();
    });

    it('answers 404 with no-store for a settings folder that no store view uses', function (): void {
        $path = 'catalog/product/cache/0123456789ab/300/' . basename($this->folder) . '/shirt.png' . $this->settings->extension;

        $response = ($this->create)($path);
        $headers = $response->getSymfonyResponse()->headers;

        expect($response->getHttpResponseCode())->toBe(404)
            ->and($headers->hasCacheControlDirective('no-store'))->toBeTrue()
            ->and($headers->hasCacheControlDirective('public'))->toBeFalse()
            ->and($response->getBody())->toBe('');
    });

    it('redirects to the product placeholder of the width with no-store when the source is missing', function (): void {
        $placeholder = Mage::getSingleton('catalog/product_image_provider')->getPlaceholderUrl(300);

        $response = ($this->create)(($this->pathOf)('missing.png', 300));
        $headers = $response->getSymfonyResponse()->headers;

        expect($placeholder)->not->toBe('')
            ->and($response->getHttpResponseCode())->toBe(302)
            ->and($headers->get('Location'))->toBe($placeholder)
            ->and($headers->hasCacheControlDirective('no-store'))->toBeTrue()
            ->and($headers->hasCacheControlDirective('public'))->toBeFalse();
    });
});
