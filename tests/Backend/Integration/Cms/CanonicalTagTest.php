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
 * Return the href of each canonical link that the CMS page block adds to the head of $page.
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

function cmsCanonicalSetBaseUrl(Mage_Core_Model_Store $store, string $url): void
{
    foreach (['web/unsecure/base_url', 'web/secure/base_url', 'web/unsecure/base_link_url', 'web/secure/base_link_url'] as $path) {
        $store->setConfig($path, $url);
    }
}

/**
 * Set the store code setting and the trailing slash setting on each of $stores.
 * The store code setting has only a default scope, so every store view must get the same value.
 */
function cmsCanonicalSetUrlConfig(string $storeInUrl, Mage_Core_Model_Store ...$stores): void
{
    foreach ($stores as $store) {
        $store->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, $storeInUrl);
        $store->setConfig('web/url/trailing_slash_behavior', 'add');
    }
}

describe('CMS page canonical tag', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->store->setConfig('web/url/trailing_slash_behavior', 'add');
        $this->pages = [];
        $this->createPage = (fn(string $prefix, ?string $robots = null): Mage_Cms_Model_Page => $this->pages[] = cmsCanonicalCreatePage($prefix . '-' . uniqid(), $robots));
        $this->createdModels = [];
        $this->createStore = function (int $groupId): Mage_Core_Model_Store {
            Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
            return $this->createdModels[] = Mage::getModel('core/store')
                ->setCode('canonical_' . substr(uniqid(), -8))
                ->setWebsiteId((int) $this->store->getWebsiteId())
                ->setGroupId($groupId)
                ->setName('Canonical store view')
                ->setIsActive()
                ->save();
        };
    });

    afterEach(function () {
        foreach ($this->pages as $page) {
            $page->delete();
        }
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        Mage::register('isSecureArea', true, true);
        foreach (array_reverse($this->createdModels) as $model) {
            $model->delete();
        }
        Mage::unregister('isSecureArea');
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

    it('keeps the trailing slash on the home page of a store in a folder when the setting removes it', function () {
        cmsCanonicalSetBaseUrl($this->store, 'https://canonical-folder.example/shop/');
        $this->store->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
        $this->store->setConfig('web/url/trailing_slash_behavior', 'remove');
        $page = ($this->createPage)('test-home-folder');
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $page->getIdentifier());

        expect(cmsCanonicalHrefs($page))->toBe(['https://canonical-folder.example/shop/']);
    });

    it('adds no canonical to the no-route page', function () {
        $page = ($this->createPage)('test-no-route');
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $page->getIdentifier() . '|' . $page->getId());

        expect(cmsCanonicalHrefs($page))->toBe([]);
    });

    it('adds no canonical to a page that is noindexed', function () {
        $page = ($this->createPage)('test-noindex', 'NOINDEX,FOLLOW');

        expect(cmsCanonicalHrefs($page))->toBe([]);
    });

    it('adds the store parameter only when the store view shares the base URL of the default store view', function (string $layout) {
        $page = ($this->createPage)('test-sibling');
        $code = ($this->createStore)((int) $this->store->getGroupId())->getCode();
        Mage::app()->reinitStores();
        $other = Mage::app()->getStore($code);
        cmsCanonicalSetUrlConfig($layout === 'store code in URL' ? '1' : '0', $other->getWebsite()->getDefaultStore(), $other);
        if ($layout === 'own base URL') {
            cmsCanonicalSetBaseUrl($other, 'https://canonical-sibling.example/');
        }
        Mage::app()->setCurrentStore($other);

        $query = $layout === 'same base URL' ? '?___store=' . $code : '';
        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $page->getIdentifier() . '/' . $query]);

        $other->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $page->getIdentifier());
        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $query]);
    })->with([
        'same base URL',
        'own base URL',
        'store code in URL',
    ]);

    it('compares the secure base URL when the storefront uses secure URLs', function () {
        $page = ($this->createPage)('test-secure');
        $code = ($this->createStore)((int) $this->store->getGroupId())->getCode();
        Mage::app()->reinitStores();
        $other = Mage::app()->getStore($code);
        $default = $other->getWebsite()->getDefaultStore();
        cmsCanonicalSetUrlConfig('0', $default, $other);
        foreach ([$default, $other] as $store) {
            $store->setConfig(Mage_Core_Model_Url::XML_PATH_SECURE_IN_FRONT, '1');
            $store->setConfig('web/secure/base_url', 'https://canonical-secure.example/');
            $store->setConfig('web/secure/base_link_url', 'https://canonical-secure.example/');
        }
        $other->setConfig('web/unsecure/base_url', 'https://canonical-unsecure.example/');
        $other->setConfig('web/unsecure/base_link_url', 'https://canonical-unsecure.example/');
        Mage::app()->setCurrentStore($other);

        expect(cmsCanonicalHrefs($page))->toBe(['https://canonical-secure.example/' . $page->getIdentifier() . '/?___store=' . $code]);
    });

    it('adds the store parameter when the store view shares the base URL of the default store view of its group', function () {
        $page = ($this->createPage)('test-group');
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        $group = $this->createdModels[] = Mage::getModel('core/store_group')
            ->setWebsiteId((int) $this->store->getWebsiteId())
            ->setName('Canonical group')
            ->setRootCategoryId((int) $this->store->getRootCategoryId())
            ->save();
        $groupDefaultModel = ($this->createStore)((int) $group->getId());
        $code = ($this->createStore)((int) $group->getId())->getCode();
        $group->setDefaultStoreId((int) $groupDefaultModel->getId())->save();
        Mage::app()->reinitStores();

        $groupDefault = Mage::app()->getStore($groupDefaultModel->getCode());
        $other = Mage::app()->getStore($code);
        cmsCanonicalSetUrlConfig('0', $other->getWebsite()->getDefaultStore(), $groupDefault, $other);
        cmsCanonicalSetBaseUrl($groupDefault, 'https://canonical-group.example/');
        cmsCanonicalSetBaseUrl($other, 'https://canonical-group.example/');

        Mage::app()->setCurrentStore($other);
        expect(cmsCanonicalHrefs($page))->toBe(['https://canonical-group.example/' . $page->getIdentifier() . '/?___store=' . $code]);

        Mage::app()->setCurrentStore($groupDefault);
        expect(cmsCanonicalHrefs($page))->toBe(['https://canonical-group.example/' . $page->getIdentifier() . '/']);
    });

    it('adds no store parameter on the default store view', function () {
        $this->store->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
        $page = ($this->createPage)('test-default-store');

        expect(cmsCanonicalHrefs($page))->toBe([Mage::getBaseUrl() . $page->getIdentifier() . '/']);
    });

    it('adds no canonical to a page without an identifier', function () {
        expect(cmsCanonicalHrefs(Mage::getModel('cms/page')))->toBe([]);
    });
});
