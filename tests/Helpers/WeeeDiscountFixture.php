<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Mage;
use Mage_Catalog_Model_Product;
use Mage_CatalogRule_Model_Rule;

/**
 * Fixtures of the tests of FPT discounts: a fee attribute, a product with a fee, a catalog rule and the cleanup.
 */
final class WeeeDiscountFixture
{
    public static function createFeeAttribute(string $code): void
    {
        Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
            'type' => 'static',
            'backend' => \Mage_Weee_Model_Attribute_Backend_Weee_Tax::getBackendModelName(),
            'input' => 'weee',
            'label' => 'Test fixed tax',
            'global' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
            'required' => false,
            'user_defined' => true,
            'group' => 'General',
        ]);
        Mage::getSingleton('eav/config')->clear();
    }

    /**
     * @param list<int> $websiteIds
     * @return array{category: \Mage_Catalog_Model_Category, product: Mage_Catalog_Model_Product, country: string}
     */
    public static function createProduct(
        string $sku,
        string $attributeCode,
        array $websiteIds = [1],
        float $price = 80.0,
        ?float $priceRange = null,
    ): array {
        $store = Mage::app()->getStore(1);
        $root = Mage::getModel('catalog/category')->setStoreId(0)->load($store->getRootCategoryId());
        $category = Mage::getModel('catalog/category')->setStoreId(0)->setName('FPT ' . $sku)
            ->setUrlKey(strtolower($sku))->setIsActive(1);
        if ($priceRange !== null) {
            $category->setData('filter_price_range', $priceRange);
        }
        $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($root->getPath())->save();

        $product = Mage::getModel('catalog/product')->setStoreId(0)
            ->setTypeId(\Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
            ->setSku($sku)
            ->setName('FPT ' . $sku)
            ->setPrice($price)
            ->setStatus(\Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTaxClassId(0)
            ->setWebsiteIds($websiteIds)
            ->setCategoryIds([(int) $category->getId()])
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20]);
        $country = (string) Mage::getModel('tax/calculation')->getRateRequest()->getCountryId();
        $fees = [];
        foreach ($websiteIds as $websiteId) {
            $fees[] = ['website_id' => $websiteId, 'country' => $country, 'state' => '*', 'price' => 10.0];
        }
        $product->setData($attributeCode, $fees);
        $product->save();

        return ['category' => $category, 'product' => $product, 'country' => $country];
    }

    /**
     * @param list<int> $websiteIds
     * @param list<int> $customerGroupIds
     */
    public static function createRule(
        Mage_Catalog_Model_Product $product,
        string $name,
        string $action = 'by_percent',
        float $amount = 10.0,
        int $sortOrder = 0,
        bool $stop = false,
        array $websiteIds = [1],
        array $customerGroupIds = [0],
        ?string $matchedSku = null,
    ): Mage_CatalogRule_Model_Rule {
        $rule = Mage::getModel('catalogrule/rule')->setName($name)
            ->setIsActive(true)
            ->setFromDate(date('Y-m-d', time() - 86400))
            ->setToDate(date('Y-m-d', time() + 86400))
            ->setWebsiteIds($websiteIds)
            ->setCustomerGroupIds($customerGroupIds)
            ->setSortOrder($sortOrder)
            ->setSimpleAction($action)
            ->setDiscountAmount($amount)
            ->setStopRulesProcessing($stop);
        $rule->loadPost(['conditions' => [
            '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
            '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $matchedSku ?? (string) $product->getSku()],
        ]]);
        $rule->save();
        return $rule;
    }

    /**
     * @param array{products: list<int>, categories: list<int>, rules: list<int>, attribute: ?string} $created
     */
    public static function cleanup(array $created): void
    {
        foreach (array_reverse($created['rules']) as $ruleId) {
            try {
                Mage::getModel('catalogrule/rule')->load($ruleId)->delete();
            } catch (\Throwable) {
            }
        }
        foreach (array_reverse($created['products']) as $productId) {
            try {
                Mage::getModel('catalog/product')->load($productId)->delete();
            } catch (\Throwable) {
            }
        }
        foreach (array_reverse($created['categories']) as $categoryId) {
            try {
                Mage::getModel('catalog/category')->load($categoryId)->delete();
            } catch (\Throwable) {
            }
        }
        if ($created['attribute']) {
            try {
                Mage::getModel('catalog/resource_eav_attribute')
                    ->loadByCode(Mage_Catalog_Model_Product::ENTITY, $created['attribute'])
                    ->delete();
                Mage::getSingleton('eav/config')->clear();
            } catch (\Throwable) {
            }
        }
    }
}
