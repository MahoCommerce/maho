<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin order create adds a product and applies a coupon through Mage_Checkout_Service_Cart with the staff
 * actor (#1348), so it follows the rules of the storefront and the API.
 */

function orderCreateCartQuote(): Mage_Sales_Model_Quote
{
    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsSuperMode(true);
    $quote->save();
    Mage::getSingleton('adminhtml/session_quote')->setStoreId(1);
    return $quote;
}

/**
 * Give $product a required text option for the time of $call, then remove it and restore the option flags.
 */
function orderCreateCartWithRequiredOption(Mage_Catalog_Model_Product $product, callable $call): mixed
{
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('catalog/product');
    $flags = $write->fetchRow($write->select()->from($table, ['has_options', 'required_options'])->where('entity_id = ?', $product->getId()));
    $option = Mage::getModel('catalog/product_option')->setProductId($product->getId())->setStoreId(0)->addData([
        'title' => 'Engraving', 'type' => 'field', 'is_require' => 1, 'price' => 0, 'price_type' => 'fixed', 'sort_order' => 0,
    ]);
    $option->save();
    $write->update($table, ['has_options' => 1, 'required_options' => 1], ['entity_id = ?' => $product->getId()]);
    try {
        return $call();
    } finally {
        $option->delete();
        $write->update($table, $flags, ['entity_id = ?' => $product->getId()]);
    }
}

beforeEach(function () {
    $this->quote = orderCreateCartQuote();
    $this->create = Mage::getModel('adminhtml/sales_order_create')->setQuote($this->quote);
    $this->cleanup = [];
});

afterEach(function () {
    foreach ($this->cleanup as $model) {
        $model->delete();
    }
    $this->quote->delete();
});

/**
 * @return array{0: Mage_Catalog_Model_Product, 1: Mage_Catalog_Model_Product}|null a variant that the storefront does
 *                                                                                 not show, and its parent
 */
function orderCreateCartVariant(): ?array
{
    $parents = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addWebsiteFilter(1)
        ->setPageSize(20);
    foreach ($parents as $parent) {
        foreach ($parent->getTypeInstance(true)->getUsedProducts(null, $parent) as $child) {
            $child = Mage::getModel('catalog/product')->setStoreId(1)->load($child->getId());
            if ((int) $child->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                && (int) $child->getVisibility() === Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
            ) {
                return [$child, Mage::getModel('catalog/product')->setStoreId(1)->load($parent->getId())];
            }
        }
    }
    return null;
}

/**
 * @return list<array{0: int, 1: bool}> the product and the "is a child line" flag of each line
 */
function orderCreateCartLines(Mage_Sales_Model_Quote $quote): array
{
    return array_map(
        fn(Mage_Sales_Model_Quote_Item $item): array => [(int) $item->getProductId(), (bool) $item->getParentItem()],
        $quote->getAllItems(),
    );
}

it('adds a variant that the storefront does not show as its configurable parent', function () {
    $variant = orderCreateCartVariant();
    if ($variant === null) {
        $this->markTestSkipped('The store has no configurable product with a variant that the storefront does not show');
    }
    [$child, $parent] = $variant;

    $this->create->addProduct((int) $child->getId(), ['qty' => 1]);

    expect(orderCreateCartLines($this->quote))->toBe([[(int) $parent->getId(), false], [(int) $child->getId(), true]]);
});

it('adds a variant as its parent when the parent misses a required option', function () {
    $variant = orderCreateCartVariant();
    if ($variant === null) {
        $this->markTestSkipped('The store has no configurable product with a variant that the storefront does not show');
    }
    [$child, $parent] = $variant;
    orderCreateCartWithRequiredOption($parent, fn() => $this->create->addProduct((int) $child->getId(), ['qty' => 1]));

    expect(orderCreateCartLines($this->quote))->toBe([[(int) $parent->getId(), false], [(int) $child->getId(), true]]);
});

it('refuses a product of another website', function () {
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-order-create-' . uniqid())->setName('Pest order create product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([2])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
    $product->save();
    $this->cleanup[] = $product;

    expect(fn() => $this->create->addProduct((int) $product->getId(), ['qty' => 1]))
        ->toThrow(Mage_Core_Exception::class, "Product '{$product->getSku()}' is not available")
        ->and($this->quote->getAllItems())->toBe([]);
});

it('applies a coupon that applies, and keeps the coupon of the cart for one that does not', function () {
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->addWebsiteFilter(1)
        ->setPageSize(1)
        ->getFirstItem();
    $this->create->addProduct((int) $product->getId(), ['qty' => 1]);
    $rule = Mage::getModel('salesrule/rule')
        ->setName('Order create coupon ' . uniqid())
        ->setIsActive()
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        ->setCouponCode('ORDER-CREATE-' . strtoupper(uniqid()))
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(10);
    $rule->save();
    $this->cleanup[] = $rule;
    $code = (string) $rule->getCouponCode();

    $this->create->applyCoupon($code);
    $applied = (string) $this->quote->getCouponCode();
    $this->create->applyCoupon('NO-SUCH-CODE');

    expect($applied)->toBe($code)
        ->and((string) $this->quote->getCouponCode())->toBe($code);
});
