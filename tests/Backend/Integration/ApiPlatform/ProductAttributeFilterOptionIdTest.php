<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/**
 * The attribute filter of the product list takes option IDs. A label in place of the ID returns a
 * 400 that names the tool for the IDs, not an empty list that a client reads as "no products".
 */

const OPTION_FILTER_SELECT_CODE = 'apitest_maker';
const OPTION_FILTER_MULTI_CODE = 'apitest_grape';
const OPTION_FILTER_SKU_PREFIX = 'apitest-optfilter-';

beforeEach(function (): void {
    StoreContext::ensureStore();

    require_once Mage::getBaseDir() . '/app/code/core/Mage/Catalog/Api/ProductProvider.php';
    require_once Mage::getBaseDir() . '/app/code/core/Mage/Catalog/Api/Product.php';

    optionFilterFixture();
});

afterAll(function (): void {
    optionFilterCleanup();
});

/**
 * @return array{select: array<string,int>, multi: array<string,int>, skus: array<string,string>}
 */
function optionFilterFixture(): array
{
    static $fixture = null;
    if ($fixture !== null) {
        return $fixture;
    }

    /** @var Mage_Catalog_Model_Resource_Setup $installer */
    $installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, OPTION_FILTER_SELECT_CODE, [
        'group' => 'General',
        'type' => 'int',
        'input' => 'select',
        'label' => 'API Test Maker',
        'source' => 'eav/entity_attribute_source_table',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'user_defined' => true,
        'required' => false,
        'option' => ['values' => ['Cantina Rossa', 'Cantina Bianca']],
    ]);
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, OPTION_FILTER_MULTI_CODE, [
        'group' => 'General',
        'type' => 'varchar',
        'backend' => 'eav/entity_attribute_backend_array',
        'input' => 'multiselect',
        'label' => 'API Test Grape',
        'source' => 'eav/entity_attribute_source_table',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'user_defined' => true,
        'required' => false,
        'option' => ['values' => ['Nebbiolo', 'Barbera']],
    ]);
    Mage::getSingleton('eav/config')->clear();

    $options = [];
    foreach ([OPTION_FILTER_SELECT_CODE, OPTION_FILTER_MULTI_CODE] as $code) {
        $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $options[$code][(string) $option['label']] = (int) $option['value'];
        }
    }

    $setId = (int) Mage::getModel('eav/entity_type')
        ->loadByCode(Mage_Catalog_Model_Product::ENTITY)
        ->getDefaultAttributeSetId();

    $skus = ['rossa' => OPTION_FILTER_SKU_PREFIX . 'rossa', 'bianca' => OPTION_FILTER_SKU_PREFIX . 'bianca'];
    $ids = [
        optionFilterCreateProduct($skus['rossa'], $setId, [
            OPTION_FILTER_SELECT_CODE => $options[OPTION_FILTER_SELECT_CODE]['Cantina Rossa'],
            OPTION_FILTER_MULTI_CODE => [$options[OPTION_FILTER_MULTI_CODE]['Nebbiolo'], $options[OPTION_FILTER_MULTI_CODE]['Barbera']],
            'options_container' => 'container2',
        ]),
        optionFilterCreateProduct($skus['bianca'], $setId, [
            OPTION_FILTER_SELECT_CODE => $options[OPTION_FILTER_SELECT_CODE]['Cantina Bianca'],
            OPTION_FILTER_MULTI_CODE => [$options[OPTION_FILTER_MULTI_CODE]['Barbera']],
            'options_container' => 'container1',
        ]),
    ];
    Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds($ids);

    $fixture = ['select' => $options[OPTION_FILTER_SELECT_CODE], 'multi' => $options[OPTION_FILTER_MULTI_CODE], 'skus' => $skus];
    return $fixture;
}

function optionFilterCreateProduct(string $sku, int $setId, array $data): int
{
    $product = Mage::getModel('catalog/product');
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($setId)
        ->setSku($sku)
        ->setName($sku)
        ->setDescription('Option filter fixture')
        ->setShortDescription('Option filter fixture')
        ->setPrice(19.99)
        ->setWeight(1)
        ->setTaxClassId(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 1, 'manage_stock' => 1, 'is_in_stock' => 1, 'qty' => 100])
        ->addData($data)
        ->save();

    return (int) $product->getId();
}

/**
 * @param array<string,mixed> $filters
 * @return list<string>
 */
function optionFilterSkus(array $filters): array
{
    $provider = new Mage\Catalog\Api\ProductProvider(null);
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

function optionFilterCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    try {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => OPTION_FILTER_SKU_PREFIX . '%']);
            foreach ($collection as $product) {
                $product->delete();
            }
        } catch (\Throwable) {
        }
        foreach ([OPTION_FILTER_SELECT_CODE, OPTION_FILTER_MULTI_CODE] as $code) {
            try {
                $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
                if ($attribute->getId()) {
                    $attribute->delete();
                }
            } catch (\Throwable) {
            }
        }
    } finally {
        Mage::unregister('isSecureArea');
    }
}

it('filters a select attribute by the option ID', function (): void {
    $fx = optionFilterFixture();

    $skus = optionFilterSkus(['attributeFilters' => Mage::helper('core')->jsonEncode([OPTION_FILTER_SELECT_CODE => (string) $fx['select']['Cantina Rossa']])]);

    expect($skus)->toContain($fx['skus']['rossa']);
    expect($skus)->not->toContain($fx['skus']['bianca']);
});

it('refuses the label of an option on a select attribute', function (): void {
    optionFilterFixture();

    optionFilterSkus(['attr_' . OPTION_FILTER_SELECT_CODE => 'Cantina Rossa']);
})->throws(BadRequestHttpException::class, OPTION_FILTER_SELECT_CODE . ' takes option IDs, not "Cantina Rossa"');

it('refuses the label of an option on a multiselect attribute', function (): void {
    $fx = optionFilterFixture();

    optionFilterSkus(['attributeFilters' => [OPTION_FILTER_MULTI_CODE => [(string) $fx['multi']['Barbera'], 'Nebbiolo']]]);
})->throws(BadRequestHttpException::class, OPTION_FILTER_MULTI_CODE . ' takes option IDs, not "Nebbiolo"');

it('refuses an array of labels on a select attribute', function (): void {
    optionFilterFixture();

    optionFilterSkus(['attributeFilters' => [OPTION_FILTER_SELECT_CODE => ['Cantina Rossa']]]);
})->throws(BadRequestHttpException::class, OPTION_FILTER_SELECT_CODE . ' takes option IDs, not "Cantina Rossa"');

it('keeps a text value of a select attribute whose options are not in the option table', function (): void {
    $fx = optionFilterFixture();

    $skus = optionFilterSkus(['attr_options_container' => 'container2']);

    expect($skus)->toContain($fx['skus']['rossa']);
    expect($skus)->not->toContain($fx['skus']['bianca']);
});
