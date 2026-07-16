<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for Mage_Eav_Model_Config::getAttributes().
 *
 * getAttributes() used to read $_entityTypeAttributes[$storeId][$entityTypeId]
 * and call array_keys() on it WITHOUT first calling _initializeStore($storeId) —
 * the guard its sibling getAttribute() has always had.
 *
 * The catch: _entityTypes is a GLOBAL list, but _entityTypeAttributes is keyed
 * per store id. Once any earlier EAV access has populated _entityTypes (e.g. an
 * admin-scope lookup during rest.php's Mage::init('admin')), getEntityType()
 * inside getAttributes() stops triggering initialization. getAttributes() then
 * called under a DIFFERENT, not-yet-initialized store scope hit array_keys(null)
 * -> TypeError, 500-ing the request. This is why the API Platform layer (a cold
 * library-mode boot that inits the admin store, then serves a frontend-scoped
 * request) surfaced it, while ordinary frontend requests — which almost always
 * initialize the current store first — did not.
 */

it('does not throw when getAttributes() is the first access for the current store scope', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    if (!$store) {
        foreach (Mage::app()->getStores() as $candidate) {
            $store = $candidate;
            break;
        }
    }
    if (!$store) {
        $this->markTestSkipped('No frontend store view available');
    }
    $frontendStoreId = (int) $store->getId();
    expect($frontendStoreId)->toBeGreaterThan(0);

    // Fresh, uninitialized config — mirrors a cold per-request singleton.
    $config = new Mage_Eav_Model_Config();

    // Prime the GLOBAL entity-type list under the ADMIN store scope (store 0),
    // exactly like rest.php's Mage::init('admin') does before any frontend work.
    // This populates _entityTypes and _entityTypeAttributes[0], but NOT the
    // frontend store's per-store index.
    $config->setCurrentStoreId(0);
    $config->getAttribute('catalog_product', 'name');

    // Switch to the frontend store whose per-store index was never built, then
    // make getAttributes() the first access for that scope. Before the fix this
    // threw "array_keys(): Argument #1 must be of type array, null given".
    $config->setCurrentStoreId($frontendStoreId);
    $attributes = $config->getAttributes('catalog_product');

    expect($attributes)->toBeArray()->not->toBeEmpty();
    expect($attributes[0])->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Abstract::class);
});

it('returns the same attribute set whether or not the store was pre-initialized', function (): void {
    $storeId = (int) Mage::app()->getStore()->getId();

    // Path A: getAttributes() is the very first access on a cold config.
    $cold = new Mage_Eav_Model_Config();
    $cold->setCurrentStoreId($storeId);
    $coldCodes = array_map(
        static fn(Mage_Eav_Model_Entity_Attribute_Abstract $a) => $a->getAttributeCode(),
        $cold->getAttributes('catalog_product'),
    );

    // Path B: the store is explicitly initialized first, then getAttributes().
    $warm = new Mage_Eav_Model_Config();
    $warm->setCurrentStoreId($storeId);
    $warm->getAttribute('catalog_product', 'name');
    $warmCodes = array_map(
        static fn(Mage_Eav_Model_Entity_Attribute_Abstract $a) => $a->getAttributeCode(),
        $warm->getAttributes('catalog_product'),
    );

    sort($coldCodes);
    sort($warmCodes);
    expect($coldCodes)->toBe($warmCodes);
    expect($coldCodes)->not->toBeEmpty();
});
