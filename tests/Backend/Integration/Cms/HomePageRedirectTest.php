<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1531: the CMS Home Page has one URL, the base URL. Its own identifier redirects there
 * with a 301, so that search engines do not index the same content twice.
 */
function homePageRedirectDispatch(string $uri, int $pageId): Mage_Core_Controller_Response_Http
{
    Mage::unregister('_singleton/cms/page');
    $symfonyRequest = SymfonyRequest::create($uri);
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setQuery($symfonyRequest->query->all());
    $request->setParam('page_id', $pageId);
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Mage_Cms_PageController($request, $response);
    $controller->viewAction();
    return $response;
}

function homePageRedirectCreatePage(string $identifier): Mage_Cms_Model_Page
{
    $page = Mage::getModel('cms/page');
    $page->setTitle('Page ' . $identifier)
        ->setIdentifier($identifier)
        ->setIsActive()
        ->setRootTemplate('one_column')
        ->setStores([0])
        ->setContent('<p>' . $identifier . '</p>')
        ->save();
    return $page;
}

describe('CMS Home Page URL', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->savedConfig = [
            Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE => $this->store->getConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE),
            'web/default/show_cms_breadcrumbs' => $this->store->getConfig('web/default/show_cms_breadcrumbs'),
        ];
        $this->home = homePageRedirectCreatePage('test-home-' . uniqid());
        $this->other = homePageRedirectCreatePage('test-other-' . uniqid());
    });

    afterEach(function () {
        foreach ($this->savedConfig as $path => $value) {
            $this->store->setConfig($path, $value);
        }
        $this->home->delete();
        $this->other->delete();
        Mage::unregister('_singleton/cms/page');
        $_GET = [];
    });

    it('redirects the identifier of the home page to the base URL with a 301', function (bool $withPageId, string $query) {
        $value = $this->home->getIdentifier() . ($withPageId ? '|' . $this->home->getId() : '');
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $value);

        $response = homePageRedirectDispatch(
            'http://localhost/' . $this->home->getIdentifier() . $query,
            (int) $this->home->getId(),
        );

        expect($response->getHttpResponseCode())->toBe(301)
            ->and($response->getSymfonyResponse()->headers->get('Location'))->toBe(Mage::getBaseUrl() . $query);
    })->with([
        'setting holds the identifier' => [false, ''],
        'setting holds the identifier and the page id' => [true, ''],
        'request has a query string' => [false, '?utm_source=news'],
    ]);

    it('renders a page that is not the home page', function () {
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $this->home->getIdentifier());

        $response = homePageRedirectDispatch(
            'http://localhost/' . $this->other->getIdentifier(),
            (int) $this->other->getId(),
        );

        expect($response->getHttpResponseCode())->toBe(200)
            ->and($response->getBody())->toContain('<p>' . $this->other->getIdentifier() . '</p>');
    });

    it('shows no breadcrumbs on the home page when the setting holds the page id', function () {
        $this->store->setConfig('web/default/show_cms_breadcrumbs', '1');
        $this->store->setConfig(
            Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE,
            $this->home->getIdentifier() . '|' . $this->home->getId(),
        );

        $layout = Mage::getModel('core/layout');
        /** @var Mage_Page_Block_Html_Breadcrumbs $breadcrumbs */
        $breadcrumbs = $layout->createBlock('page/html_breadcrumbs', 'breadcrumbs');
        $layout->createBlock('cms/page', 'cms_page', ['page' => $this->home]);

        expect($breadcrumbs->getCrumbs())->toBe([]);
    });
});
