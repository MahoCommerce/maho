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
 * The attribute filter of the product list takes the label of an option as well as its ID, so a
 * client that knows only the label, such as the admin assistant, finds the products.
 */

const LABEL_SELECT_CODE = 'apitest_maker';
const LABEL_MULTI_CODE = 'apitest_grape';
const LABEL_SKU_PREFIX = 'apitest-label-';

beforeEach(function (): void {
    StoreContext::ensureStore();

    require_once Mage::getBaseDir() . '/app/code/core/Mage/Catalog/Api/ProductProvider.php';
    require_once Mage::getBaseDir() . '/app/code/core/Mage/Catalog/Api/Product.php';

    labelFixture();
});

afterAll(function (): void {
    labelCleanup();
});

/**
 * @return array{select: array<string,int>, multi: array<string,int>, skus: array<string,string>}
 */
function labelFixture(): array
{
    static $fixture = null;
    if ($fixture !== null) {
        return $fixture;
    }

    /** @var Mage_Catalog_Model_Resource_Setup $installer */
    $installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, LABEL_SELECT_CODE, [
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
    $installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, LABEL_MULTI_CODE, [
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
    foreach ([LABEL_SELECT_CODE, LABEL_MULTI_CODE] as $code) {
        $attribute = Mage::getModel('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $options[$code][(string) $option['label']] = (int) $option['value'];
        }
    }

    $setId = (int) Mage::getModel('eav/entity_type')
        ->loadByCode(Mage_Catalog_Model_Product::ENTITY)
        ->getDefaultAttributeSetId();

    $skus = ['rossa' => LABEL_SKU_PREFIX . 'rossa', 'bianca' => LABEL_SKU_PREFIX . 'bianca'];
    $ids = [
        labelCreateProduct($skus['rossa'], $setId, [
            LABEL_SELECT_CODE => $options[LABEL_SELECT_CODE]['Cantina Rossa'],
            LABEL_MULTI_CODE => [$options[LABEL_MULTI_CODE]['Nebbiolo'], $options[LABEL_MULTI_CODE]['Barbera']],
        ]),
        labelCreateProduct($skus['bianca'], $setId, [
            LABEL_SELECT_CODE => $options[LABEL_SELECT_CODE]['Cantina Bianca'],
            LABEL_MULTI_CODE => [$options[LABEL_MULTI_CODE]['Barbera']],
        ]),
    ];
    Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds($ids);

    $fixture = ['select' => $options[LABEL_SELECT_CODE], 'multi' => $options[LABEL_MULTI_CODE], 'skus' => $skus];
    return $fixture;
}

function labelCreateProduct(string $sku, int $setId, array $data): int
{
    $product = Mage::getModel('catalog/product');
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId($setId)
        ->setSku($sku)
        ->setName($sku)
        ->setDescription('Label filter fixture')
        ->setShortDescription('Label filter fixture')
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
function labelFilterSkus(array $filters): array
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

function labelCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    try {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToFilter('sku', ['like' => LABEL_SKU_PREFIX . '%']);
            foreach ($collection as $product) {
                $product->delete();
            }
        } catch (\Throwable) {
        }
        foreach ([LABEL_SELECT_CODE, LABEL_MULTI_CODE] as $code) {
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

it('filters a select attribute by the label of an option', function (): void {
    $fx = labelFixture();

    $skus = labelFilterSkus(['attributeFilters' => Mage::helper('core')->jsonEncode([LABEL_SELECT_CODE => 'Cantina Rossa'])]);

    expect($skus)->toContain($fx['skus']['rossa']);
    expect($skus)->not->toContain($fx['skus']['bianca']);
});

it('matches the label in any letter case', function (): void {
    $fx = labelFixture();

    $skus = labelFilterSkus(['attr_' . LABEL_SELECT_CODE => 'cantina BIANCA']);

    expect($skus)->toContain($fx['skus']['bianca']);
    expect($skus)->not->toContain($fx['skus']['rossa']);
});

it('still filters a select attribute by the option ID', function (): void {
    $fx = labelFixture();

    $skus = labelFilterSkus(['attr_' . LABEL_SELECT_CODE => (string) $fx['select']['Cantina Bianca']]);

    expect($skus)->toContain($fx['skus']['bianca']);
    expect($skus)->not->toContain($fx['skus']['rossa']);
});

it('filters a multiselect attribute by the labels of its options', function (): void {
    $fx = labelFixture();

    $skus = labelFilterSkus(['attributeFilters' => [LABEL_MULTI_CODE => ['Nebbiolo']]]);

    expect($skus)->toContain($fx['skus']['rossa']);
    expect($skus)->not->toContain($fx['skus']['bianca']);
});

it('refuses a label that no option has', function (): void {
    labelFixture();

    labelFilterSkus(['attr_' . LABEL_SELECT_CODE => 'Cantina Verde']);
})->throws(BadRequestHttpException::class, 'has no option "Cantina Verde"');
