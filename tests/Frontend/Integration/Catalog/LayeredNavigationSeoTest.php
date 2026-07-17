<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Integration coverage for the layered-navigation SEO crawl controls (PR #972).
 *
 * Unlike a logic-only test, these exercise the real moving parts: the real category
 * block and EAV category model, the live filterable-attribute collection queried from
 * the database, the real product-list toolbar, real store configuration, and the Page
 * head block's robots fallback chain. Full layout/template rendering is intentionally
 * avoided, and the category is built in-memory so the suite does not depend on any
 * sample-data row surviving cross-suite test ordering.
 */
describe('Layered Navigation SEO', function () {
    beforeEach(function () {
        $this->block = new Mage_Catalog_Block_Category_View();
        $this->helper = Mage::helper('catalog/category');
        $this->request = Mage::app()->getRequest();

        // A real catalog/category model, populated in memory. getForcedRobots() and
        // shouldUseCanonicalTag() only read getMetaRobots() off the current category, so
        // an unsaved instance exercises the same code paths without coupling the test to a
        // persisted sample-data row (which earlier suites in the run can mutate).
        $this->category = Mage::getModel('catalog/category');
        $this->block->setData('current_category', $this->category);
    });

    describe('filterable request vars (live attribute collection)', function () {
        test('the category filter plus real filterable attribute codes are discovered', function () {
            $vars = $this->block->getFilterableRequestVars();
            // 'cat' is always included; the rest come from the real product attribute
            // collection filtered by addIsFilterableFilter(), so known sample-data
            // filterables must appear.
            expect($vars)
                ->toContain('cat')
                ->toContain('color')
                ->toContain('price')
                ->toContain('manufacturer');
        });

        test('a non-filterable attribute code is not treated as a filter var', function () {
            // 'sku' is a product attribute but is not flagged filterable.
            expect($this->block->getFilterableRequestVars())->not->toContain('sku');
        });
    });

    describe('filter detection (live request)', function () {
        test('no active filters on a clean request', function () {
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('a real filterable attribute param is an active filter', function () {
            $this->request->setParam('color', '123');
            expect($this->block->hasActiveFilters())->toBeTrue();
        });

        test('toolbar params (pagination, sort, mode, limit) are not filters', function () {
            $this->request->setParam('p', '2');
            $this->request->setParam('order', 'price');
            $this->request->setParam('dir', 'asc');
            $this->request->setParam('mode', 'grid');
            $this->request->setParam('limit', '24');
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('an empty filter value does not count as active', function () {
            $this->request->setParam('color', '');
            expect($this->block->hasActiveFilters())->toBeFalse();
        });
    });

    describe('pagination detection (live toolbar)', function () {
        test('the first page is not paginated', function () {
            expect($this->block->isPaginated())->toBeFalse();
        });

        test('p=1 is not paginated', function () {
            $this->request->setParam('p', '1');
            expect($this->block->isPaginated())->toBeFalse();
        });

        test('p>1 is paginated, read through the toolbar page var', function () {
            // isPaginated() resolves the page var from catalog/product_list_toolbar,
            // falling back to the default 'p' when no toolbar block is in scope.
            $this->request->setParam('p', '3');
            expect($this->block->isPaginated())->toBeTrue();
        });
    });

    describe('forced robots on a real category', function () {
        test('a base category view forces no robots directive', function () {
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('a filtered view forces NOINDEX,FOLLOW', function () {
            $this->request->setParam('color', '123');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('a cat-only filtered view forces NOINDEX,FOLLOW', function () {
            $this->request->setParam('cat', '5');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('a paginated view forces NOINDEX,FOLLOW', function () {
            $this->request->setParam('p', '2');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('an explicit category NOINDEX is preserved verbatim, not weakened to FOLLOW', function () {
            $this->category->setMetaRobots('NOINDEX,NOFOLLOW');
            $this->request->setParam('color', '123');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,NOFOLLOW');
        });

        test('an explicit category INDEX is overridden to NOINDEX,FOLLOW on a paginated view', function () {
            $this->category->setMetaRobots('INDEX,FOLLOW');
            $this->request->setParam('p', '2');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('an explicit category INDEX leaves the base view indexable', function () {
            $this->category->setMetaRobots('INDEX,FOLLOW');
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('an indexable facet landing page (#971) forces no robots even when filtered and paginated', function () {
            Mage::register(Mage_Catalog_Helper_Category::REGISTRY_LN_LANDING_PAGE, true);
            $this->request->setParam('color', '123');
            $this->request->setParam('p', '2');
            expect($this->block->getForcedRobots())->toBeNull();
        });
    });

    describe('canonical decision (real category)', function () {
        test('an indexable base category advertises a canonical', function () {
            expect($this->block->shouldUseCanonicalTag())->toBeTrue();
        });

        test('a filtered view suppresses the canonical (mutually exclusive with NOINDEX)', function () {
            $this->request->setParam('color', '123');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a paginated view suppresses the canonical', function () {
            $this->request->setParam('p', '2');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('the canonical follows the category_canonical_tag flag', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_USE_CATEGORY_CANONICAL_TAG, '0');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a category with its own NOINDEX also suppresses the canonical', function () {
            $this->category->setMetaRobots('NOINDEX,FOLLOW');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a facet landing page (#971) manages its own canonical', function () {
            Mage::register(Mage_Catalog_Helper_Category::REGISTRY_LN_LANDING_PAGE, true);
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });
    });

    describe('head block robots fallback (real Page head)', function () {
        test('a registered category meta_robots is surfaced by the head block', function () {
            $this->category->setMetaRobots('NOINDEX,FOLLOW');
            Mage::register('current_category', $this->category);
            $head = new Mage_Page_Block_Html_Head();
            expect($head->getRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('with no entity robots the head falls back to the store default', function () {
            Mage::app()->getStore()->setConfig('design/head/default_robots', 'INDEX,FOLLOW');
            $head = new Mage_Page_Block_Html_Head();
            expect($head->getRobots())->toBe('INDEX,FOLLOW');
        });

        test('an explicitly set robots value wins over the fallback chain', function () {
            $head = new Mage_Page_Block_Html_Head();
            $head->setRobots('NOINDEX,NOFOLLOW');
            expect($head->getRobots())->toBe('NOINDEX,NOFOLLOW');
        });
    });

    describe('configuration defaults and toggles (real store config)', function () {
        test('the category canonical tag and all crawl controls are enabled by default', function () {
            expect($this->helper->canUseCanonicalTag())->toBeTrue();
            expect($this->helper->canUseNoindexForFilteredPages())->toBeTrue();
            expect($this->helper->canUseNoindexForPaginatedPages())->toBeTrue();
            expect($this->helper->canUseNofollowForFilterLinks())->toBeTrue();
        });

        test('disabling the filtered-page toggle stops forcing robots on a filtered view', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOINDEX_FILTERED, '0');
            $this->request->setParam('color', '123');
            expect($this->helper->canUseNoindexForFilteredPages())->toBeFalse();
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('disabling the paginated-page toggle stops forcing robots on a paginated view', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOINDEX_PAGINATED, '0');
            $this->request->setParam('p', '2');
            expect($this->helper->canUseNoindexForPaginatedPages())->toBeFalse();
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('the nofollow filter-links toggle honors store config', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOFOLLOW_FILTER_LINKS, '0');
            expect($this->helper->canUseNofollowForFilterLinks())->toBeFalse();
        });
    });
});
