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

/**
 * Filter block stub that renders catalog/layer/filter.phtml with injected items,
 * avoiding the need for a real attribute model or product fixtures.
 */
class LayeredNavigationSeoFilterStub extends Mage_Catalog_Block_Layer_Filter_Attribute
{
    /** @var array */
    public $stubItems = [];

    #[\Override]
    public function getItems()
    {
        return $this->stubItems;
    }

    #[\Override]
    public function shouldDisplayProductCount()
    {
        return false;
    }
}

function layeredNavMakeFilterItem(string $requestVar): Mage_Catalog_Model_Layer_Filter_Item
{
    $item = new Mage_Catalog_Model_Layer_Filter_Item();
    $item->setFilter(new DataObject(['request_var' => $requestVar]));
    return $item;
}

function layeredNavRenderFilterHtml(): string
{
    Mage::getDesign()->setArea('frontend');
    $block = new LayeredNavigationSeoFilterStub();
    $block->setLayout(Mage::app()->getLayout());
    $block->stubItems = [
        new DataObject(['count' => 3, 'url' => 'http://example.com/shoes.html?color=red', 'label' => 'Red']),
    ];
    return $block->toHtml();
}

describe('Layered Navigation SEO', function () {
    beforeEach(function () {
        $this->block = Mage::app()->getLayout()->createBlock('catalog/category_view');
        $this->helper = Mage::helper('catalog/category');
    });

    describe('pagination detection', function () {
        test('the first page is not considered paginated', function () {
            Mage::app()->getRequest()->setParam('p', null);
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
        test('no active filters when the layer state is empty', function () {
            Mage::getSingleton('catalog/layer')->getState()->setFilters([]);
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('the category (cat) filter alone does not count as an active filter', function () {
            Mage::getSingleton('catalog/layer')->getState()
                ->setFilters([layeredNavMakeFilterItem('cat')]);
            expect($this->block->hasActiveFilters())->toBeFalse();
        });

        test('an attribute filter counts as an active filter', function () {
            Mage::getSingleton('catalog/layer')->getState()
                ->setFilters([layeredNavMakeFilterItem('color')]);
            expect($this->block->hasActiveFilters())->toBeTrue();
        });

        test('a category filter combined with an attribute filter is active', function () {
            Mage::getSingleton('catalog/layer')->getState()->setFilters([
                layeredNavMakeFilterItem('cat'),
                layeredNavMakeFilterItem('color'),
            ]);
            expect($this->block->hasActiveFilters())->toBeTrue();
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

    describe('filter link rel="nofollow" rendering', function () {
        test('filter links carry rel="nofollow" when the control is enabled', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOFOLLOW_FILTER_LINKS, '1');
            expect(layeredNavRenderFilterHtml())->toContain('rel="nofollow"');
        });

        test('filter links omit rel="nofollow" when the control is disabled', function () {
            Mage::app()->getStore()->setConfig(Mage_Catalog_Helper_Category::XML_PATH_LN_NOFOLLOW_FILTER_LINKS, '0');
            expect(layeredNavRenderFilterHtml())->not->toContain('rel="nofollow"');
        });
    });
});
