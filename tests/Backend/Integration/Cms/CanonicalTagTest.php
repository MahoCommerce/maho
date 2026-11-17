<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1860: a CMS page puts <link rel="canonical"> in the head.
 */
function cmsCanonicalCreatePage(string $identifier, ?string $robots = null): Mage_Cms_Model_Page
{
    $page = Mage::getModel('cms/page');
    $page->setTitle('Page ' . $identifier)
        ->setIdentifier($identifier)
        ->setIsActive()
        ->setRootTemplate('one_column')
        ->setStores([0])
        ->setContent('<p>' . $identifier . '</p>');
    if ($robots !== null) {
        $page->setData('meta_robots', $robots);
    }
    $page->save();
    return $page;
}

/**
 * Render the head of $page and return the href of each canonical link in it.
 *
 * @return list<string>
 */
function cmsCanonicalHrefs(Mage_Cms_Model_Page $page): array
{
    $layout = Mage::getModel('core/layout');
    /** @var Mage_Page_Block_Html_Head $head */
    $head = $layout->createBlock('page/html_head', 'head');
    $layout->createBlock('cms/page', 'cms_page', ['page' => $page]);

    $hrefs = [];
    foreach ($head->getData('items') ?? [] as $item) {
        if ($item['type'] === 'link_rel' && $item['params'] === 'rel="canonical"') {
            $hrefs[] = $item['name'];
        }
    }
    return $hrefs;
}

describe('CMS page canonical tag', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->savedConfig = [];
        foreach ([
            Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE,
            Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE,
            'web/url/trailing_slash_behavior',
            Mage_Core_Model_Store::XML_PATH_STORE_IN_URL,
        ] as $path) {
            $this->savedConfig[$path] = $this->store->getConfig($path);
        }
        $this->store->setConfig('web/url/trailing_slash_behavior', 'add');
        $this->pages = [];
        $this->createPage = function (string $prefix, ?string $robots = null): Mage_Cms_Model_Page {
            return $this->pages[] = cmsCanonicalCreatePage($prefix . '-' . uniqid(), $robots);
        };
    });

    afterEach(function () {
        foreach ($this->savedConfig as $path => $value) {
            $this->store->setConfig($path, $value);
        }
        foreach ($this->pages as $page) {
            $page->delete();
        }
        Mage::app()->setCurrentStore($this->store);
    });

    it('points a page to its own URL', function () {
        $page = ($this->createPage)('test-canonical');

        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $page->getIdentifier() . '/']);
    });

    it('follows the trailing slash setting', function () {
        $this->store->setConfig('web/url/trailing_slash_behavior', 'remove');
        $page = ($this->createPage)('test-canonical');

        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $page->getIdentifier()]);
    });

    it('points the home page to the base URL', function (bool $withPageId) {
        $page = ($this->createPage)('test-home');
        $this->store->setConfig(
            Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE,
            $page->getIdentifier() . ($withPageId ? '|' . $page->getId() : ''),
        );

        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl()]);
    })->with([
        'setting holds the identifier' => [false],
        'setting holds the identifier and the page id' => [true],
    ]);

    it('adds no canonical to the no-route page', function () {
        $page = ($this->createPage)('test-no-route');
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $page->getIdentifier() . '|' . $page->getId());

        expect(cmsCanonicalHrefs($page))->toBe([]);
    });

    it('adds no canonical to a page that is noindexed', function () {
        $page = ($this->createPage)('test-noindex', 'NOINDEX,FOLLOW');

        expect(cmsCanonicalHrefs($page))->toBe([]);
    });

    it('keeps the store parameter on a store view that shares the base URL of the default store view', function (bool $ownBaseUrl) {
        $page = ($this->createPage)('test-sibling');
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        $sibling = Mage::getModel('core/store')
            ->setCode('canonical_' . substr(uniqid(), -8))
            ->setWebsiteId((int) $this->store->getWebsiteId())
            ->setGroupId((int) $this->store->getGroupId())
            ->setName('Canonical sibling')
            ->setIsActive()
            ->save();
        Mage::app()->reinitStores();

        try {
            $other = Mage::app()->getStore($sibling->getCode());
            $other->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
            $other->setConfig('web/url/trailing_slash_behavior', 'add');
            if ($ownBaseUrl) {
                foreach (['web/unsecure/base_url', 'web/secure/base_url', 'web/unsecure/base_link_url', 'web/secure/base_link_url'] as $path) {
                    $other->setConfig($path, 'https://canonical-sibling.example/');
                }
            }
            Mage::app()->setCurrentStore($other);

            $expected = $ownBaseUrl
                ? 'https://canonical-sibling.example/' . $page->getIdentifier() . '/'
                : Mage::getBaseUrl() . $page->getIdentifier() . '/?___store=' . $other->getCode();
            expect(cmsCanonicalHrefs($page))->toBe([$expected]);
        } finally {
            Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
            Mage::register('isSecureArea', true, true);
            Mage::getModel('core/store')->load($sibling->getId())->delete();
            Mage::unregister('isSecureArea');
            Mage::app()->reinitStores();
            $this->store = Mage::app()->getDefaultStoreView();
        }
    })->with([
        'same base URL' => [false],
        'own base URL' => [true],
    ]);

    it('adds no store parameter on the default store view', function () {
        $this->store->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
        $page = ($this->createPage)('test-default-store');

        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $page->getIdentifier() . '/']);
    });

    it('adds no canonical to a page without an identifier', function () {
        expect(cmsCanonicalHrefs(Mage::getModel('cms/page')))->toBe([]);
    });
});
