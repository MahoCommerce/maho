<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    Mage::setIsDeveloperMode(true);
});

it('loads the catalog category collection with attribute joins under strict GROUP BY', function () {
    // Reproduces the failure from issue #688: when a category collection
    // is loaded with addAttributeToSelect for a non-admin store, the EAV
    // attribute loader joins t_d/t_s tables for "default vs store" value
    // resolution and groups by (entity_id, attribute_id). Under
    // ONLY_FULL_GROUP_BY, t_d.value / t_s.value / t_s.attribute_id were
    // non-grouped non-aggregated columns until Mysql helper's
    // wrapForGroupBy() was made dev-mode-aware.
    $collection = Mage::getResourceModel('catalog/category_collection');
    $collection
        ->addAttributeToSelect(['name', 'is_active', 'url_key'])
        ->setStore(1);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
    expect($collection->getSize())->toBeGreaterThan(0);
});

it('loads the catalog product collection with addAttributeToSelect under strict GROUP BY', function () {
    // The product collection uses the same EAV attribute-loader code path.
    $collection = Mage::getResourceModel('catalog/product_collection');
    $collection
        ->addAttributeToSelect(['name', 'price', 'sku'])
        ->setStore(1)
        ->setPageSize(5);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

it('loads the customer collection with addAttributeToSelect under strict GROUP BY', function () {
    $collection = Mage::getResourceModel('customer/customer_collection');
    $collection
        ->addAttributeToSelect(['firstname', 'lastname', 'email'])
        ->setPageSize(5);

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
});

it('reports strict GROUP BY as required when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $helper = Mage::getResourceHelper('eav');
    expect($helper->requiresStrictGroupBy())->toBeTrue();
});

it('reports strict GROUP BY as not required when developer mode is off', function () {
    Mage::setIsDeveloperMode(false);
    $helper = Mage::getResourceHelper('eav');
    expect($helper->requiresStrictGroupBy())->toBeFalse();
});
