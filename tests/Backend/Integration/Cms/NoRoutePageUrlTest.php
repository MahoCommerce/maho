<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1652: the CMS No Route Page shows only for a URL that does not exist. Its own
 * identifier forwards to the no-route action, which answers 404, so that search engines
 * do not index it.
 */
describe('CMS No Route Page URL', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->savedNoRoute = $this->store->getConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE);
        $identifier = 'test-no-route-' . uniqid();
        $this->page = Mage::getModel('cms/page');
        $this->page->setTitle('Page ' . $identifier)
            ->setIdentifier($identifier)
            ->setIsActive()
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p>' . $identifier . '</p>')
            ->save();
    });

    afterEach(function () {
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $this->savedNoRoute);
        $this->page->delete();
        Mage::unregister('_singleton/cms/page');
    });

    it('forwards the identifier of the no-route page to the no-route action', function (bool $withPageId) {
        $value = $this->page->getIdentifier() . ($withPageId ? '|' . $this->page->getId() : '');
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $value);

        Mage::unregister('_singleton/cms/page');
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/' . $this->page->getIdentifier()),
        );
        $request->setParam('page_id', (int) $this->page->getId());
        $response = new Mage_Core_Controller_Response_Http();
        new Mage_Cms_PageController($request, $response)->viewAction();

        expect($request->getActionName())->toBe('noRoute')
            ->and($request->isDispatched())->toBeFalse()
            ->and($response->getBody())->not->toContain('<p>' . $this->page->getIdentifier() . '</p>');
    })->with([
        'setting holds the identifier' => [false],
        'setting holds the identifier and the page id' => [true],
    ]);

    it('leaves the forward to the no-route action to the default router', function () {
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/' . $this->page->getIdentifier()),
        );
        $request->setModuleName('cms')->setControllerName('page')->setActionName('view')->initForward();
        $request->setActionName('noRoute')->setDispatched(false);

        expect(new Mage_Cms_Controller_Router()->match($request))->toBeFalse()
            ->and($request->getControllerName())->toBe('page')
            ->and($request->getActionName())->toBe('noRoute')
            ->and($request->isDispatched())->toBeFalse();
    });

    it('does not resolve the identifier of the no-route page in the URL resolver API', function () {
        $resolve = fn(): string => new Mage\Core\Api\UrlResolverProvider()
            ->provide(new ApiPlatform\Metadata\Get(), [], ['filters' => ['path' => $this->page->getIdentifier()]])
            ->type;
        expect($resolve())->toBe('cms_page');

        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $this->page->getIdentifier() . '|' . $this->page->getId());

        expect($resolve())->toBe('not_found');
    });
});
