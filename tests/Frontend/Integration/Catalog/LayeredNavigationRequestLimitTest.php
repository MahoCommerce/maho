<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoFrontendTestCase::class);

/**
 * A bot that ignores robots.txt and nofollow requests every filter combination. Each
 * combination is a new URL, so the store refuses unknown values and too many values with a
 * 404 before it loads the products.
 */
function lnlRequest(string $query): Mage_Core_Controller_Request_Http
{
    return new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/category.html?' . $query));
}

function lnlAttribute(string $code): Mage_Catalog_Model_Resource_Eav_Attribute
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
    expect($attribute)->toBeInstanceOf(Mage_Catalog_Model_Resource_Eav_Attribute::class);

    return clone $attribute;
}

/**
 * @return list<string>
 */
function lnlColorOptionIds(): array
{
    $ids = array_map(
        fn(array $option): string => (string) $option['value'],
        lnlAttribute('color')->getSource()->getAllOptions(false),
    );
    expect(count($ids))->toBeGreaterThanOrEqual(3);

    return $ids;
}

function lnlParentCategory(): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('level', 2)
        ->addAttributeToFilter('children_count', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();
    expect((int) $category->getId())->toBeGreaterThan(0);

    return Mage::getModel('catalog/category')->load($category->getId());
}

function lnlLayer(Mage_Catalog_Model_Category $category): Mage_Catalog_Model_Layer
{
    return new class ($category) extends Mage_Catalog_Model_Layer {
        public function __construct(Mage_Catalog_Model_Category $category)
        {
            parent::__construct();
            $this->setData('current_category', $category);
        }

        #[\Override]
        public function getFilterableAttributes()
        {
            return [lnlAttribute('color'), lnlAttribute('price')];
        }
    };
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

describe('attribute filter values', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel(lnlAttribute('color'));
        $this->options = lnlColorOptionIds();
    });

    test('a request without the parameter applies no value', function () {
        expect($this->filter->countRequestValues(lnlRequest('p=2')))->toBe(0);
    });

    test('an existing option is one value', function () {
        expect($this->filter->countRequestValues(lnlRequest('color=' . $this->options[0])))->toBe(1);
    });

    test('an option that does not exist is refused', function () {
        expect($this->filter->countRequestValues(lnlRequest('color=99999999')))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('color=abc')))->toBeNull();
    });

    test('another spelling of an option ID is refused', function () {
        expect($this->filter->countRequestValues(lnlRequest('color=0' . $this->options[0])))->toBeNull();
    });

    test('a single-select filter refuses a list of values', function () {
        $query = 'color=' . $this->options[0] . ',' . $this->options[1];
        expect($this->filter->countRequestValues(lnlRequest($query)))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('color[]=' . $this->options[0])))->toBeNull();
    });

    test('a multi-select filter counts each value', function () {
        $attribute = lnlAttribute('color')->setIsFilterableMultiple(1);
        $filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel($attribute);

        $query = 'color=' . $this->options[0] . ',' . $this->options[1];
        expect($filter->countRequestValues(lnlRequest($query)))->toBe(2);
    });

    test('a multi-select filter refuses repeated, padded and unknown values', function () {
        $attribute = lnlAttribute('color')->setIsFilterableMultiple(1);
        $filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel($attribute);
        $first = $this->options[0];

        expect($filter->countRequestValues(lnlRequest("color={$first},{$first}")))->toBeNull();
        expect($filter->countRequestValues(lnlRequest("color={$first},%20{$this->options[1]}")))->toBeNull();
        expect($filter->countRequestValues(lnlRequest("color={$first},")))->toBeNull();
        expect($filter->countRequestValues(lnlRequest("color={$first},99999999")))->toBeNull();
    });
});

describe('category filter values', function () {
    beforeEach(function () {
        $this->parent = lnlParentCategory();
        $this->filter = Mage::getModel('catalog/layer_filter_category')->setLayer(lnlLayer($this->parent));
    });

    test('a category below the current category is one value', function () {
        $childId = (int) explode(',', (string) $this->parent->getChildren())[0];
        expect($this->filter->countRequestValues(lnlRequest('cat=' . $childId)))->toBe(1);
    });

    test('the current category, another branch and a bad number are refused', function () {
        $otherBranch = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToFilter('level', 2)
            ->addAttributeToFilter('entity_id', ['neq' => $this->parent->getId()])
            ->setPageSize(1)
            ->getFirstItem();

        expect($this->filter->countRequestValues(lnlRequest('cat=' . $this->parent->getId())))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('cat=' . $otherBranch->getId())))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('cat=99999999')))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('cat=0')))->toBeNull();
        expect($this->filter->countRequestValues(lnlRequest('cat=abc')))->toBeNull();
    });
});

describe('price and decimal filter values', function () {
    test('a price range of numbers is one value', function () {
        $filter = Mage::getModel('catalog/layer_filter_price')->setAttributeModel(lnlAttribute('price'));

        expect($filter->countRequestValues(lnlRequest('price=10-20')))->toBe(1);
        expect($filter->countRequestValues(lnlRequest('price=100-')))->toBe(1);
        expect($filter->countRequestValues(lnlRequest('price=-20')))->toBe(1);
    });

    test('a price range that is not two numbers is refused', function () {
        $filter = Mage::getModel('catalog/layer_filter_price')->setAttributeModel(lnlAttribute('price'));

        expect($filter->countRequestValues(lnlRequest('price=10')))->toBeNull();
        expect($filter->countRequestValues(lnlRequest('price=5abc-10')))->toBeNull();
        expect($filter->countRequestValues(lnlRequest('price=10-20,abc')))->toBeNull();
        expect($filter->countRequestValues(lnlRequest('price[]=10-20')))->toBeNull();
    });

    test('a decimal filter takes an index and a range', function () {
        $filter = Mage::getModel('catalog/layer_filter_decimal')->setRequestVar('weight');

        expect($filter->countRequestValues(lnlRequest('weight=2,10')))->toBe(1);
        expect($filter->countRequestValues(lnlRequest('weight=2')))->toBeNull();
        expect($filter->countRequestValues(lnlRequest('weight=0,10')))->toBeNull();
        expect($filter->countRequestValues(lnlRequest('weight=02,10')))->toBeNull();
    });
});

describe('the limit of the layer', function () {
    beforeEach(function () {
        $this->layer = lnlLayer(lnlParentCategory());
        $this->options = lnlColorOptionIds();
    });

    test('the default limit is 7 selected filters', function () {
        expect($this->layer->getMaxSelectedFilters())->toBe(7);
    });

    test('a request with values up to the limit is valid', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '2');

        expect($this->layer->isValidRequest(lnlRequest('color=' . $this->options[0] . '&price=10-20')))->toBeTrue();
    });

    test('a request with more values than the limit is refused', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '1');

        expect($this->layer->isValidRequest(lnlRequest('color=' . $this->options[0] . '&price=10-20')))->toBeFalse();
    });

    test('a limit of 0 accepts any number of valid values', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '0');

        expect($this->layer->isValidRequest(lnlRequest('color=' . $this->options[0] . '&price=10-20')))->toBeTrue();
    });

    test('one unknown value makes the request invalid', function () {
        expect($this->layer->isValidRequest(lnlRequest('color=99999999')))->toBeFalse();
    });

    test('a filter value in the URL path is checked like a query value', function () {
        $request = lnlRequest('')->setParam('color', '99999999');

        expect($this->layer->isValidRequest($request))->toBeFalse();
    });

    test('a decimal attribute is checked with the decimal filter', function () {
        $layer = new class (lnlParentCategory()) extends Mage_Catalog_Model_Layer {
            public function __construct(Mage_Catalog_Model_Category $category)
            {
                parent::__construct();
                $this->setData('current_category', $category);
            }

            #[\Override]
            public function getFilterableAttributes()
            {
                return [lnlAttribute('weight')];
            }
        };

        expect($layer->isValidRequest(lnlRequest('weight=2,10')))->toBeTrue();
    });

    test('parameters that are not filters do not count', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '1');

        expect($this->layer->isValidRequest(lnlRequest('p=2&dir=asc&utm_source=x&color=' . $this->options[0])))->toBeTrue();
    });

    test('the limit is reached when the state holds as many values as the limit', function () {
        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '2');
        $filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel(lnlAttribute('color'));
        $item = fn(string $value) => Mage::getModel('catalog/layer_filter_item')->setFilter($filter)->setValue($value);

        $this->layer->getState()->addFilter($item($this->options[0]));
        expect($this->layer->isFilterLimitReached())->toBeFalse();

        $this->layer->getState()->addFilter($item($this->options[1]));
        expect($this->layer->isFilterLimitReached())->toBeTrue();
    });
});

describe('layered navigation block', function () {
    test('the options are hidden when the limit is reached', function () {
        $block = new class extends Mage_Catalog_Block_Layer_View {
            public Mage_Catalog_Model_Layer $testLayer;

            #[\Override]
            public function getLayer()
            {
                return $this->testLayer;
            }

            #[\Override]
            public function getFilters()
            {
                return [new \Maho\DataObject(['items_count' => 1])];
            }
        };
        $layer = lnlLayer(lnlParentCategory());
        $block->testLayer = $layer;
        $options = lnlColorOptionIds();
        $filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel(lnlAttribute('color'));
        foreach ([$options[0], $options[1]] as $value) {
            $layer->getState()->addFilter(Mage::getModel('catalog/layer_filter_item')->setFilter($filter)->setValue($value));
        }

        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '0');
        expect($block->canShowOptions())->toBeTrue();

        Mage::app()->getStore()->setConfig(Mage_Catalog_Model_Layer::XML_PATH_MAX_SELECTED_FILTERS, '2');
        expect($block->canShowOptions())->toBeFalse();
    });
});

describe('category page', function () {
    test('an unknown filter value forwards to the 404 page before the layout loads', function () {
        $category = lnlParentCategory();
        expect(Mage::helper('catalog/category')->canShow($category))->toBeTrue();
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('/catalog/category/view/id/' . $category->getId() . '?cat=99999999'),
        );
        $request->setParam('id', $category->getId())
            ->setRouteName('catalog')
            ->setControllerName('category')
            ->setActionName('view')
            ->setControllerModule('Mage_Catalog')
            ->setDispatched(true);
        Mage::app()->setRequest($request);

        $controller = new Mage_Catalog_CategoryController($request, new Mage_Core_Controller_Response_Http());
        $controller->viewAction();

        expect($request->getActionName())->toBe('noRoute');
        expect($request->isDispatched())->toBeFalse();
        expect($controller->getLayout()->getAllBlocks())->toBe([]);
    });
});

describe('search result page', function () {
    test('an unknown filter value forwards to the 404 page before the layout loads', function () {
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('/catalogsearch/result/?q=shirt&cat=99999999'),
        );
        $request->setRouteName('catalogsearch')
            ->setControllerName('result')
            ->setActionName('index')
            ->setControllerModule('Mage_CatalogSearch')
            ->setDispatched(true);
        Mage::app()->setRequest($request);

        $controller = new Mage_CatalogSearch_ResultController($request, new Mage_Core_Controller_Response_Http());
        $controller->indexAction();

        expect($request->getActionName())->toBe('noRoute');
        expect($controller->getLayout()->getAllBlocks())->toBe([]);
    });
});
