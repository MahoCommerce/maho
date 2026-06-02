<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\DataObject;

uses(Tests\MahoFrontendTestCase::class);

describe('Layered Navigation SEO', function () {
    beforeEach(function () {
        // Instantiate directly rather than through the shared layout: these tests only
        // need the block's logic methods.
        $this->block = new Mage_Catalog_Block_Category_View();
        $this->helper = Mage::helper('catalog/category');
    });

    describe('pagination detection', function () {
        test('the first page is not considered paginated', function () {
            expect($this->block->isPaginated())->toBeFalse();
        });

        test('p=1 is not considered paginated', function () {
            Mage::app()->getRequest()->setParam('p', 1);
            expect($this->block->isPaginated())->toBeFalse();
        });

        test('p>1 is considered paginated', function () {
            Mage::app()->getRequest()->setParam('p', 2);
            expect($this->block->isPaginated())->toBeTrue();
        });
    });

    describe('filter detection', function () {
        test('no active filters when the request carries no filter params', function () {
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('toolbar params (pagination/sort) are not filters', function () {
            Mage::app()->getRequest()->setParam('p', 2);
            Mage::app()->getRequest()->setParam('order', 'price');
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('the category (cat) filter counts as an active filter like any other', function () {
            Mage::app()->getRequest()->setParam('cat', 5);
            expect($this->block->hasActiveFilters())->toBeTrue();
        });

        test('a filterable attribute param counts as an active filter', function () {
            Mage::app()->getRequest()->setParam('color', 'red');
            expect($this->block->hasActiveFilters())->toBeTrue();
        });

        test('an empty filter value does not count as active', function () {
            Mage::app()->getRequest()->setParam('color', '');
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('the filterable request vars include cat and a known attribute', function () {
            expect($this->block->getFilterableRequestVars())
                ->toContain('cat')
                ->toContain('color');
        });
    });

    describe('forced robots directive', function () {
        beforeEach(function () {
            // A category with no explicit Meta Robots by default.
            $this->block->setData('current_category', new DataObject());
        });

        test('a base category page forces no robots directive', function () {
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('a filtered page forces NOINDEX,FOLLOW', function () {
            Mage::app()->getRequest()->setParam('color', 'red');
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('a cat-only filtered page forces NOINDEX,FOLLOW like any other filter', function () {
            Mage::app()->getRequest()->setParam('cat', 5);
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('a paginated page forces NOINDEX,FOLLOW', function () {
            Mage::app()->getRequest()->setParam('p', 2);
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('an explicit category INDEX is overridden to NOINDEX,FOLLOW on filtered/paginated views', function () {
            $this->block->setData('current_category', new DataObject(['meta_robots' => 'INDEX,FOLLOW']));
            Mage::app()->getRequest()->setParam('p', 2);
            // A duplicate view is noindexed regardless of the base category being indexable.
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
        });

        test('an explicit category NOINDEX is preserved verbatim, not weakened', function () {
            $this->block->setData('current_category', new DataObject(['meta_robots' => 'NOINDEX,NOFOLLOW']));
            Mage::app()->getRequest()->setParam('color', 'red');
            // The admin's exact directive wins, so NOFOLLOW is not flipped to FOLLOW.
            expect($this->block->getForcedRobots())->toBe('NOINDEX,NOFOLLOW');
        });

        test('an explicit category INDEX leaves the base view indexable', function () {
            $this->block->setData('current_category', new DataObject(['meta_robots' => 'INDEX,FOLLOW']));
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('the filtered-page toggle is honored', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOINDEX_FILTERED, '0');
            Mage::app()->getRequest()->setParam('color', 'red');
            expect($this->block->getForcedRobots())->toBeNull();
        });

        test('an indexable facet landing page (#971) forces no robots even when filtered/paginated', function () {
            Mage::register(Mage_Catalog_Helper_Category::REGISTRY_LN_LANDING_PAGE, true);
            Mage::app()->getRequest()->setParam('color', 'red');
            Mage::app()->getRequest()->setParam('p', 2);
            expect($this->block->getForcedRobots())->toBeNull();
        });
    });

    describe('canonical decision', function () {
        beforeEach(function () {
            // A category with no explicit Meta Robots by default.
            $this->block->setData('current_category', new DataObject());
        });

        test('an indexable base category advertises a canonical', function () {
            expect($this->block->shouldUseCanonicalTag())->toBeTrue();
        });

        test('a filtered page suppresses the canonical, mutually exclusive with NOINDEX', function () {
            Mage::app()->getRequest()->setParam('color', 'red');
            // The two signals never co-occur: NOINDEX is forced and the canonical is dropped.
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a paginated page suppresses the canonical', function () {
            Mage::app()->getRequest()->setParam('p', 2);
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('the canonical follows the category_canonical_tag flag', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_USE_CATEGORY_CANONICAL_TAG, '0');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a facet landing page (#971) manages its own canonical', function () {
            Mage::register(Mage_Catalog_Helper_Category::REGISTRY_LN_LANDING_PAGE, true);
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a category with its own NOINDEX Meta Robots also suppresses the canonical', function () {
            $this->block->setData('current_category', new DataObject(['meta_robots' => 'NOINDEX,FOLLOW']));
            // getForcedRobots() surfaces the category's own noindex, so the canonical is
            // suppressed to keep robots and canonical mutually exclusive.
            expect($this->block->getForcedRobots())->toBe('NOINDEX,FOLLOW');
            expect($this->block->shouldUseCanonicalTag())->toBeFalse();
        });

        test('a category with an explicit INDEX Meta Robots still advertises a canonical', function () {
            $this->block->setData('current_category', new DataObject(['meta_robots' => 'INDEX,FOLLOW']));
            expect($this->block->shouldUseCanonicalTag())->toBeTrue();
        });
    });

    describe('configuration defaults', function () {
        test('the category canonical tag is enabled by default', function () {
            expect($this->helper->canUseCanonicalTag())->toBeTrue();
        });

        test('all layered navigation SEO controls are enabled by default', function () {
            expect($this->helper->canUseNoindexForFilteredPages())->toBeTrue();
            expect($this->helper->canUseNoindexForPaginatedPages())->toBeTrue();
            expect($this->helper->canUseNofollowForFilterLinks())->toBeTrue();
        });
    });

    describe('configuration toggles', function () {
        test('noindex for filtered pages honors the store config flag', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOINDEX_FILTERED, '0');
            expect($this->helper->canUseNoindexForFilteredPages())->toBeFalse();
        });

        test('noindex for paginated pages honors the store config flag', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOINDEX_PAGINATED, '0');
            expect($this->helper->canUseNoindexForPaginatedPages())->toBeFalse();
        });

        test('nofollow for filter links honors the store config flag', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOFOLLOW_FILTER_LINKS, '0');
            expect($this->helper->canUseNofollowForFilterLinks())->toBeFalse();
        });
    });
});
