<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The storefront blocks show a linked product only when it is visible in the catalog, in a website
 * of the store and, mostly, in stock. A rule must skip the other products before it applies the
 * maximum links, or the page shows fewer products than the rule allows.
 */
function clsfProduct(string $sku, array $data = [], bool $inStock = true): int
{
    $product = Mage::getModel('catalog/product');
    $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
    $product->setName($sku);
    $product->setSku($sku);
    $product->setPrice(10.00);
    $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
    $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
    $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
    $product->setAttributeSetId(4);
    $product->setWebsiteIds([1]);
    $product->setStockData([
        'use_config_manage_stock' => 0,
        'manage_stock' => 1,
        'qty' => $inStock ? 10 : 0,
        'is_in_stock' => $inStock ? 1 : 0,
    ]);
    $product->addData($data);
    $product->save();

    return (int) $product->getId();
}

function clsfSkuCondition(string $type, string $operator, string $value): array
{
    return ['type' => $type, 'attribute' => 'sku', 'operator' => $operator, 'value' => $value];
}

/**
 * A rule whose source products have a SKU that contains $sourceToken, linked to the products with
 * a SKU that contains $targetToken.
 */
function clsfRule(int $linkTypeId, string $sourceToken, string $targetToken): Maho_CatalogLinkRule_Model_Rule
{
    $rule = Mage::getModel('cataloglinkrule/rule');
    $rule->setName('clsf-' . uniqid());
    $rule->setLinkTypeId($linkTypeId);
    $rule->setIsActive(true);
    $rule->setPriority(0);
    $rule->setSortOrder('name_asc');
    $rule->getConditions()->loadArray([
        'type' => 'cataloglinkrule/rule_source_combine',
        'aggregator' => 'all',
        'value' => 1,
        'conditions' => [clsfSkuCondition('cataloglinkrule/rule_source_product', '{}', $sourceToken)],
    ]);
    $rule->getActions()->loadArray([
        'type' => 'cataloglinkrule/rule_target_combine',
        'aggregator' => 'all',
        'value' => 1,
        'conditions' => [clsfSkuCondition('cataloglinkrule/rule_target_product', '{}', $targetToken)],
    ]);

    return $rule;
}

/**
 * @return int[] linked product IDs
 */
function clsfLinkedIds(int $sourceId, int $linkTypeId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return array_map(intval(...), $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_link'), ['linked_product_id'])
            ->where('product_id = ?', $sourceId)
            ->where('link_type_id = ?', $linkTypeId),
    ));
}

function clsfShowOutOfStock(bool $show): void
{
    Mage::app()->getStore()->setConfig(Mage_CatalogInventory_Helper_Data::XML_PATH_SHOW_OUT_OF_STOCK, $show ? '1' : '0');
}

describe('CatalogLinkRule targets that the storefront can show', function () {
    beforeEach(function () {
        Mage::app()->getStore()->setConfig(
            Maho_CatalogLinkRule_Model_Processor::CONFIG_MERGE_MODE,
            Maho_CatalogLinkRule_Model_Processor::MODE_REPLACE,
        );
        clsfShowOutOfStock(false);
    });

    test('the maximum links count only targets that the product page shows', function () {
        $token = 'clsf-' . uniqid();
        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL;

        $source = clsfProduct("$token-src");
        // Names sort the hidden targets first, so without the filters they take the only slot
        $notVisible = clsfProduct("$token-a", ['visibility' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE]);
        $outOfStock = clsfProduct("$token-b", [], false);
        $noWebsite = clsfProduct("$token-c", ['website_ids' => []]);
        $shown = clsfProduct("$token-d");

        $rule = clsfRule($linkType, "$token-src", $token);
        $rule->setMaxLinks(1);
        $rule->save();

        Mage::getModel('cataloglinkrule/processor')->applyRules($linkType);
        $linked = clsfLinkedIds($source, $linkType);
        $rule->delete();

        expect($linked)->toBe([$shown])
            ->and($linked)->not->toContain($notVisible, $outOfStock, $noWebsite);
    });

    test('a source product in no website gets no links', function () {
        $token = 'clsf-' . uniqid();
        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED;

        $source = clsfProduct("$token-src", ['website_ids' => []]);
        clsfProduct("$token-a");

        $rule = clsfRule($linkType, "$token-src", $token);
        $rule->save();

        Mage::getModel('cataloglinkrule/processor')->applyRules($linkType);
        $linked = clsfLinkedIds($source, $linkType);
        $rule->delete();

        expect($linked)->toBe([]);
    });

    test('out of stock targets follow "Display Out of Stock Products", except for cross-sells', function () {
        $token = 'clsf-' . uniqid();
        $inStock = clsfProduct("$token-a");
        $outOfStock = clsfProduct("$token-b", [], false);

        clsfShowOutOfStock(true);

        $upsell = clsfRule(Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL, 'none', $token);
        $crosssell = clsfRule(Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL, 'none', $token);

        expect($upsell->getMatchingTargetProductIds(null, [1]))->toBe([$inStock, $outOfStock])
            ->and($crosssell->getMatchingTargetProductIds(null, [1]))->toBe([$inStock]);
    });

    test('an empty website list matches no target', function () {
        $token = 'clsf-' . uniqid();
        clsfProduct("$token-a");

        $rule = clsfRule(Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED, 'none', $token);

        expect($rule->getMatchingTargetProductIds(null, []))->toBe([])
            ->and($rule->getMatchingTargetProductIds(null, [1]))->not->toBe([]);
    });
});
