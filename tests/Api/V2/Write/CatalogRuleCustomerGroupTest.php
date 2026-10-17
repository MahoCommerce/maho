<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 catalog rule prices follow the caller's customer group (WRITE)
 *
 * A rule for NOT LOGGED IN only must lower the guest price and leave the price of a
 * logged-in customer alone on every path that reads a final price, including the
 * paths that find no customer group on the product.
 *
 * @group write
 */

const GROUP_RULE_NAME = 'pest-api-rule-customer-group';

function groupRuleRead(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_read');
}

function groupRuleTable(string $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

function groupRuleProduct(int $id): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')->setStoreId(1)->load($id);
}

function groupRuleReapply(array $productIds): void
{
    foreach ($productIds as $productId) {
        Mage::getModel('catalogrule/rule')->applyAllRulesToProduct($productId);
    }
    Mage::app()->cleanCache(['API_PRODUCTS']);
}

/**
 * @return array{grouped: int, groupedChild: int, bundle: int, selection: int, linkedFrom: int, linked: int}
 */
function groupRuleSampleProducts(): array
{
    $read = groupRuleRead();
    $website = groupRuleTable('catalog/product_website');

    $grouped = (int) $read->fetchOne(
        $read->select()
            ->from(['e' => groupRuleTable('catalog/product')], 'entity_id')
            ->join(['w' => $website], 'w.product_id = e.entity_id AND w.website_id = 1', [])
            ->where('e.type_id = ?', Mage_Catalog_Model_Product_Type::TYPE_GROUPED)
            ->order('e.entity_id')
            ->limit(1),
    );
    $groupedChild = (int) groupRuleProduct($grouped)->getTypeInstance(true)
        ->getAssociatedProducts(groupRuleProduct($grouped))[0]->getId();

    // A dynamic price bundle, and one of its selections that the price index holds
    $selectionRow = $read->fetchRow(
        $read->select()
            ->from(['s' => groupRuleTable('bundle/selection')], ['bundle' => 'parent_product_id', 'selection' => 'product_id'])
            ->join(['e' => groupRuleTable('catalog/product')], 'e.entity_id = s.parent_product_id', [])
            ->join(['w' => $website], 'w.product_id = e.entity_id AND w.website_id = 1', [])
            ->join(['i' => groupRuleTable('catalog/product_index_price')], 'i.entity_id = s.product_id AND i.website_id = 1 AND i.customer_group_id = 0', [])
            ->where('e.type_id = ?', Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
            ->order(['s.parent_product_id', 's.product_id'])
            ->limit(1),
    );
    $bundle = (int) $selectionRow['bundle'];
    expect((int) groupRuleProduct($bundle)->getPriceType())->toBe(Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC);

    $linkedFrom = (int) $read->fetchOne(
        $read->select()
            ->from(['l' => groupRuleTable('catalog/product_link')], 'product_id')
            ->where('l.link_type_id = ?', Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED)
            ->order('l.product_id')
            ->limit(1),
    );
    $related = Mage::getModel('catalog/product')->setStoreId(1)->load($linkedFrom)->getRelatedProductCollection()
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());
    Mage::getModel('cataloginventory/stock_status')->addIsInStockFilterToCollection($related);

    return [
        'grouped' => $grouped,
        'groupedChild' => $groupedChild,
        'bundle' => $bundle,
        'selection' => (int) $selectionRow['selection'],
        'linkedFrom' => $linkedFrom,
        'linked' => (int) $related->getFirstItem()->getId(),
    ];
}

function groupRuleReindexPrice(int $productId): void
{
    Mage::getSingleton('index/indexer')->processEntityAction(
        new Maho\DataObject(['id' => $productId]),
        Mage_Catalog_Model_Product::ENTITY,
        Mage_Catalog_Model_Product_Indexer_Price::EVENT_TYPE_REINDEX_PRICE,
    );
    Mage::app()->cleanCache(['API_PRODUCTS']);
}

function groupRuleRow(array $rows, string $key, int $id): array
{
    foreach ($rows as $row) {
        if ((int) ($row[$key] ?? 0) === $id) {
            return $row;
        }
    }
    return [];
}

beforeAll(function (): void {
    Mage::app();
});

beforeEach(function (): void {
    $this->ids = groupRuleSampleProducts();
    $ruled = [$this->ids['groupedChild'], $this->ids['selection'], $this->ids['linked']];
    expect($ruled)->each->toBeGreaterThan(0);

    $this->fullPrice = [];
    foreach ($ruled as $productId) {
        $this->fullPrice[$productId] = (float) groupRuleProduct($productId)->getPrice();
    }

    $rule = Mage::getModel('catalogrule/rule');
    $rule->setName(GROUP_RULE_NAME)
        ->setIsActive(true)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([Mage_Customer_Model_Group::NOT_LOGGED_IN_ID])
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(50)
        ->setStopRulesProcessing(false);
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => [
            'type' => 'catalogrule/rule_condition_product',
            'attribute' => 'sku',
            'operator' => '()',
            'value' => implode(',', array_map(fn($id) => groupRuleProduct($id)->getSku(), $ruled)),
        ],
    ]]);
    $rule->save();
    groupRuleReapply($ruled);

    $this->ruled = $ruled;
    $this->customerToken = customerToken();
});

afterEach(function (): void {
    foreach (Mage::getResourceModel('catalogrule/rule_collection')->addFieldToFilter('name', GROUP_RULE_NAME) as $rule) {
        $rule->delete();
    }
    groupRuleReapply($this->ruled ?? []);
    cleanupTestData();
});

it('prices the product found by url key for the caller group', function (): void {
    $productId = $this->ids['linked'];
    $path = '/api/rest/v2/products?urlKey=' . urlencode((string) groupRuleProduct($productId)->getUrlKey());

    $guest = apiGet($path);
    $customer = apiGet($path, $this->customerToken);

    expect((float) groupRuleRow($guest['json']['member'] ?? $guest['json'], 'id', $productId)['finalPrice'])->toBe($this->fullPrice[$productId] / 2)
        ->and((float) groupRuleRow($customer['json']['member'] ?? $customer['json'], 'id', $productId)['finalPrice'])->toBe($this->fullPrice[$productId]);
});

it('prices related products for the caller group', function (): void {
    $productId = $this->ids['linked'];
    $path = "/api/rest/v2/products/{$this->ids['linkedFrom']}";

    $guest = apiGet($path)['json']['relatedProducts'] ?? [];
    $customer = apiGet($path, $this->customerToken)['json']['relatedProducts'] ?? [];

    expect((float) groupRuleRow($guest, 'id', $productId)['finalPrice'])->toBe($this->fullPrice[$productId] / 2)
        ->and((float) groupRuleRow($customer, 'id', $productId)['finalPrice'])->toBe($this->fullPrice[$productId]);
});

it('prices grouped children and the grouped minimal price for the caller group', function (): void {
    $childId = $this->ids['groupedChild'];
    $path = "/api/rest/v2/products/{$this->ids['grouped']}";

    $guest = apiGet($path)['json'];
    $customer = apiGet($path, $this->customerToken)['json'];

    expect((float) groupRuleRow($guest['groupedProducts'], 'id', $childId)['finalPrice'])->toBe($this->fullPrice[$childId] / 2)
        ->and((float) groupRuleRow($customer['groupedProducts'], 'id', $childId)['finalPrice'])->toBe($this->fullPrice[$childId])
        ->and((float) $customer['minimalPrice'])->toBe(min(array_map(
            fn(array $child) => (float) $child['finalPrice'],
            $customer['groupedProducts'],
        )));
});

it('prices dynamic bundle selections for the caller group', function (): void {
    $selectionId = $this->ids['selection'];
    $path = "/api/rest/v2/products/{$this->ids['bundle']}";
    $selectionPrice = function (array $product) use ($selectionId): float {
        foreach ($product['bundleOptions'] as $option) {
            if ($row = groupRuleRow($option['selections'], 'productId', $selectionId)) {
                return (float) $row['price'];
            }
        }
        return 0.0;
    };

    expect($selectionPrice(apiGet($path)['json']))->toBe($this->fullPrice[$selectionId] / 2)
        ->and($selectionPrice(apiGet($path, $this->customerToken)['json']))->toBe($this->fullPrice[$selectionId]);
});

it('prices wishlist items for the wishlist owner group', function (): void {
    $productId = $this->ids['linked'];
    $added = apiPost('/api/rest/v2/customers/me/wishlist', ['productId' => $productId, 'qty' => 1], $this->customerToken);
    expect($added['status'])->toBeIn([200, 201]);
    trackCreated('wishlist_item', (int) $added['json']['id']);

    $items = apiGet('/api/rest/v2/customers/me/wishlist', $this->customerToken)['json'];
    $items = $items['member'] ?? $items;

    expect((float) groupRuleRow($items, 'productId', $productId)['productFinalPrice'])->toBe($this->fullPrice[$productId]);
});

it('applies the group price of the caller group to related products', function (): void {
    $productId = $this->ids['linked'];
    $groupId = (int) Mage::getModel('customer/customer')->load((int) fixtures('customer_id'))->getGroupId();
    $groupPrice = round($this->fullPrice[$productId] * 0.8, 2);
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = groupRuleTable('catalog/product_attribute_group_price');
    $where = ['entity_id = ?' => $productId, 'customer_group_id = ?' => $groupId, 'website_id = ?' => 0];
    $existing = $write->fetchAll(
        $write->select()->from($table)->where('entity_id = ?', $productId)->where('customer_group_id = ?', $groupId)->where('website_id = ?', 0),
    );
    $write->delete($table, $where);
    $write->insert($table, [
        'entity_id' => $productId,
        'all_groups' => 0,
        'customer_group_id' => $groupId,
        'value' => $groupPrice,
        'website_id' => 0,
    ]);

    try {
        groupRuleReindexPrice($productId);
        $path = "/api/rest/v2/products/{$this->ids['linkedFrom']}";

        $guest = apiGet($path)['json']['relatedProducts'] ?? [];
        $customer = apiGet($path, $this->customerToken)['json']['relatedProducts'] ?? [];

        expect((float) groupRuleRow($guest, 'id', $productId)['finalPrice'])->toBe($this->fullPrice[$productId] / 2)
            ->and((float) groupRuleRow($customer, 'id', $productId)['finalPrice'])->toBe($groupPrice);
    } finally {
        $write->delete($table, $where);
        foreach ($existing as $row) {
            $write->insert($table, $row);
        }
        groupRuleReindexPrice($productId);
    }
});

it('prices a guest cart for guests when a customer token adds to it', function (): void {
    $productId = $this->ids['linked'];
    $groupId = (int) Mage::getModel('customer/customer')->load((int) fixtures('customer_id'))->getGroupId();
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = groupRuleTable('catalog/product_attribute_group_price');
    $where = ['entity_id = ?' => $productId, 'customer_group_id = ?' => $groupId, 'website_id = ?' => 0];
    $existing = $write->fetchAll(
        $write->select()->from($table)->where('entity_id = ?', $productId)->where('customer_group_id = ?', $groupId)->where('website_id = ?', 0),
    );
    $write->delete($table, $where);
    $write->insert($table, [
        'entity_id' => $productId,
        'all_groups' => 0,
        'customer_group_id' => $groupId,
        'value' => round($this->fullPrice[$productId] * 0.3, 2),
        'website_id' => 0,
    ]);

    try {
        groupRuleReindexPrice($productId);
        $maskedId = apiPost('/api/rest/v2/guest-carts', [])['json']['maskedId'];
        trackCreated('quote', (int) Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne(
            'SELECT entity_id FROM ' . groupRuleTable('sales/quote') . ' WHERE masked_quote_id = ?',
            [$maskedId],
        ));
        $sku = (string) groupRuleProduct($productId)->getSku();
        expect(apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", ['sku' => $sku, 'qty' => 1], $this->customerToken)['status'])->toBeIn([200, 201]);

        $cart = apiGet("/api/rest/v2/guest-carts/{$maskedId}", $this->customerToken)['json'];

        expect((float) $cart['items'][0]['price'])->toBe($this->fullPrice[$productId] / 2);
    } finally {
        $write->delete($table, $where);
        foreach ($existing as $row) {
            $write->insert($table, $row);
        }
        groupRuleReindexPrice($productId);
    }
});

it('prices a dynamic bundle in a customer cart with the rules of the customer group', function (): void {
    $bundle = groupRuleProduct($this->ids['bundle']);
    $type = $bundle->getTypeInstance(true);
    $options = [];
    $ruledQty = 1.0;
    foreach ($type->getSelectionsCollection($type->getOptionsIds($bundle), $bundle) as $selection) {
        $optionId = (int) $selection->getOptionId();
        if ((int) $selection->getProductId() === $this->ids['selection']) {
            $options[$optionId] = (int) $selection->getSelectionId();
            $ruledQty = (float) ($selection->getSelectionQty() ?: 1);
        } elseif ($selection->isSalable() && !isset($options[$optionId])) {
            $options[$optionId] = (int) $selection->getSelectionId();
        }
    }
    $item = ['sku' => $bundle->getSku(), 'qty' => 1, 'bundleOption' => $options];

    $maskedId = apiPost('/api/rest/v2/guest-carts', [])['json']['maskedId'];
    trackCreated('quote', (int) Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne(
        'SELECT entity_id FROM ' . groupRuleTable('sales/quote') . ' WHERE masked_quote_id = ?',
        [$maskedId],
    ));
    expect(apiPost("/api/rest/v2/guest-carts/{$maskedId}/items", $item)['status'])->toBeIn([200, 201]);
    $guestPrice = (float) apiGet("/api/rest/v2/guest-carts/{$maskedId}")['json']['items'][0]['price'];

    $cartId = (int) apiPost('/api/rest/v2/carts', [], $this->customerToken)['json']['id'];
    trackCreated('quote', $cartId);
    expect(apiPost("/api/rest/v2/carts/{$cartId}/items", $item, $this->customerToken)['status'])->toBeIn([200, 201]);
    $customerPrice = (float) apiGet("/api/rest/v2/carts/{$cartId}", $this->customerToken)['json']['items'][0]['price'];

    expect(round($customerPrice - $guestPrice, 2))
        ->toBe(round($this->fullPrice[$this->ids['selection']] * $ruledQty / 2, 2));
});
