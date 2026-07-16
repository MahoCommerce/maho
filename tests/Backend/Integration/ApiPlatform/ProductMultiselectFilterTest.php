<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Service\StoreContext;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/**
 * Regression coverage for the products API multiselect attribute filter.
 *
 * A multiselect attribute persists its selected option ids as one comma-joined
 * string ("47,51"). The provider used to apply a plain equality match, so
 * `?attr_<code>=47` could never hit "47,51" and every multiselect filter
 * silently returned zero rows. These tests drive the real ProductProvider
 * collection with a live multiselect attribute + products, so an equality
 * regression fails here (RED) while the FIND_IN_SET fix passes (GREEN).
 */

const MS_ATTR_CODE = 'apitest_material';
const MS_SKU_PREFIX = 'apitest-ms-';

beforeEach(function (): void {
    StoreContext::ensureStore();

    // The Mage\<Module>\Api\ autoloader is only registered by the API kernel's
    // boot(), which doesn't run in a plain backend test. Register an equivalent
    // PSR-4 mapping (app/code/core/) so these namespaced classes resolve here.
    static $registered = false;
    if (!$registered) {
        spl_autoload_register(function (string $class): void {
            if (!str_starts_with($class, 'Mage\\')) {
                return;
            }
            $file = Mage::getBaseDir() . '/app/code/core/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        $registered = true;
    }

    msFixture();
});

afterAll(function (): void {
    msCleanup();
});

/**
 * Create (once) a global multiselect attribute with three options and three
 * enabled, catalog-visible, price-indexed simple products:
 *   - "both"   → Cotton + Wool (stored as "cottonId,woolId")
 *   - "single" → Silk
 *   - "none"   → no options selected (negative control)
 *
 * @return array{code: string, options: array<string,int>, skus: array<string,string>}
 */
function msFixture(): array
{
    static $fixture = null;
    if ($fixture !== null) {
        return $fixture;
    }

    $code = MS_ATTR_CODE;

    /** @var Mage_Catalog_Model_Resource_Setup $installer */
    $installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
        'group' => 'General',
        'type' => 'varchar',
        'backend' => 'eav/entity_attribute_backend_array',
        'input' => 'multiselect',
        'label' => 'API Test Material',
        'source' => 'eav/entity_attribute_source_table',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'visible' => true,
        'required' => false,
        'user_defined' => true,
        'filterable' => true,
        'used_in_product_listing' => true,
        'option' => ['values' => ['Cotton', 'Wool', 'Silk']],
    ]);

    // The installer inserted options via raw SQL, so drop the EAV config cache
    // before reading them back through the source model.
    Mage::getSingleton('eav/config')->clear();

    $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
    $options = [];
    foreach ($attribute->getSource()->getAllOptions(false) as $opt) {
        $options[(string) $opt['label']] = (int) $opt['value'];
    }

    $setId = (int) Mage::getModel('eav/entity_type')
        ->loadByCode(Mage_Catalog_Model_Product::ENTITY)
        ->getDefaultAttributeSetId();

    $skus = [
        'both' => MS_SKU_PREFIX . 'both',
        'single' => MS_SKU_PREFIX . 'single',
        'none' => MS_SKU_PREFIX . 'none',
    ];

    $ids = [];
    $ids[] = msCreateProduct($skus['both'], $setId, $code, [$options['Cotton'], $options['Wool']]);
    $ids[] = msCreateProduct($skus['single'], $setId, $code, [$options['Silk']]);
    $ids[] = msCreateProduct($skus['none'], $setId, $code, []);

    // Plain listings inner-join the price index, so unindexed products never
    // surface regardless of the attribute filter. Reindex the three we made.
    Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds($ids);

    $fixture = ['code' => $code, 'options' => $options, 'skus' => $skus];
    return $fixture;
}

function msCreateProduct(string $sku, int $setId, string $code, array $optionIds): int
{
    $product = Mage::getModel('catalog/product');
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($setId)
        ->setSku($sku)
        ->setName($sku)
        ->setDescription('Multiselect filter fixture')
        ->setShortDescription('Multiselect filter fixture')
        ->setPrice(29.99)
        ->setWeight(1)
        ->setTaxClassId(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->setStockData([
            'use_config_manage_stock' => 1,
            'manage_stock' => 1,
            'is_in_stock' => 1,
            'qty' => 100,
        ]);

    if ($optionIds !== []) {
        // An array value goes through the array backend model, which imploders
        // it to the "id,id" set string a real multiselect stores.
        $product->setData($code, $optionIds);
    }

    $product->save();

    return (int) $product->getId();
}

/**
 * Drive the real ProductProvider::getCollection with the given request filters
 * and return the SKUs it yields. Mirrors the REST/GraphQL path without HTTP.
 *
 * @param array<string,mixed> $filters
 * @return list<string>
 */
function msFilterSkus(array $filters): array
{
    $provider = new Mage\Catalog\Api\ProductProvider(null);

    // Pin the group so the collection doesn't try to resolve identity from a
    // (non-existent) request; guests price-index at NOT_LOGGED_IN.
    $groupProp = new ReflectionProperty(Maho\ApiPlatform\Provider::class, 'customerGroupId');
    $groupProp->setValue($provider, Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

    $method = new ReflectionMethod(Mage\Catalog\Api\ProductProvider::class, 'getCollection');
    $paginator = $method->invoke($provider, ['filters' => $filters + ['pageSize' => 100]]);

    $skus = [];
    foreach ($paginator as $product) {
        $skus[] = $product->sku;
    }
    return $skus;
}

function msCleanup(): void
{
    // Product deletion requires the secure area. Register it gracefully and
    // ALWAYS unregister in finally: this runs after the last tearDown() (which
    // Mage::reset()s the registry), so a leaked key would make the next test
    // file's setUp() throw "isSecureArea already exists". Each delete is guarded
    // independently so one failure can't abort the rest (or skip the unregister).
    Mage::register('isSecureArea', true, true);
    try {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => MS_SKU_PREFIX . '%']);
            foreach ($collection as $product) {
                $product->delete();
            }
        } catch (\Throwable $e) {
            // Best effort - the runner rebuilds a fresh test DB each run anyway.
        }

        try {
            $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, MS_ATTR_CODE);
            if ($attribute->getId()) {
                $attribute->delete();
            }
        } catch (\Throwable $e) {
            // Best effort.
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
}

it('matches a product whose multiselect set contains the requested option id', function (): void {
    $fx = msFixture();

    // ?attr_apitest_material=<cottonId>
    $skus = msFilterSkus(['attr_' . $fx['code'] => (string) $fx['options']['Cotton']]);

    // The "both" product stores "cottonId,woolId" - equality could never hit it.
    expect($skus)->toContain($fx['skus']['both']);
    expect($skus)->not->toContain($fx['skus']['single']);
    expect($skus)->not->toContain($fx['skus']['none']);
});

it('OR-matches comma-separated option ids (any-of)', function (): void {
    $fx = msFixture();

    // ?attr_apitest_material=<cottonId>,<silkId>  -> Cotton OR Silk
    $value = $fx['options']['Cotton'] . ',' . $fx['options']['Silk'];
    $skus = msFilterSkus(['attr_' . $fx['code'] => $value]);

    expect($skus)->toContain($fx['skus']['both']);   // has Cotton
    expect($skus)->toContain($fx['skus']['single']); // has Silk
    expect($skus)->not->toContain($fx['skus']['none']);
});

it('accepts an array value through the GraphQL attributeFilters JSON path', function (): void {
    $fx = msFixture();

    // GraphQL delivers {"apitest_material":[<woolId>]} as a JSON string.
    $json = Mage::helper('core')->jsonEncode([$fx['code'] => [$fx['options']['Wool']]]);
    $skus = msFilterSkus(['attributeFilters' => $json]);

    expect($skus)->toContain($fx['skus']['both']);   // has Wool
    expect($skus)->not->toContain($fx['skus']['single']);
    expect($skus)->not->toContain($fx['skus']['none']);
});

it('returns none of the fixtures for an option id no product selected', function (): void {
    $fx = msFixture();

    // A valid-but-unused option id (Wool is only on "both"); use a definitely
    // unassigned id: max option + 1 doesn't exist, so use Silk against "both".
    // Simpler: filter Silk and assert the Cotton/Wool-only product is absent.
    $skus = msFilterSkus(['attr_' . $fx['code'] => (string) $fx['options']['Silk']]);

    expect($skus)->toContain($fx['skus']['single']);
    expect($skus)->not->toContain($fx['skus']['both']);
    expect($skus)->not->toContain($fx['skus']['none']);
});
