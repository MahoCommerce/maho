<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * These listeners copy cart data to the order. The test loads the global events only, as the
 * REST API and the CLI do, and places the order through the service that the REST API uses.
 */

/**
 * @return array{orders: list<int>, quotes: list<int>, products: list<int>, files: list<string>}
 */
function &orderDataCreated(): array
{
    static $created = ['orders' => [], 'quotes' => [], 'products' => [], 'files' => []];
    return $created;
}

function orderDataPlace(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Order
{
    $cart = Mage::getService('checkout/cart');
    $address = $cart->mapAddressInput([
        'firstName' => 'Test', 'lastName' => 'Buyer', 'street' => ['123 Test St'], 'city' => 'Los Angeles',
        'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ]);
    $cart->applyBillingAddress($quote, $address);
    if (!$quote->isVirtual()) {
        $cart->applyShippingAddress($quote, $address);
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
    }
    $cart->collectAndVerifyTotals($quote);
    $cart->importPaymentData($quote, 'checkmo', null, Mage_Payment_Model_Method_Abstract::checksForCurrentScope());
    $quote->save();

    $order = Mage::getService('sales/order')->place($quote, 'buyer@example.com')['order'];
    $created = &orderDataCreated();
    $created['orders'][] = (int) $order->getId();

    return Mage::getModel('sales/order')->load($order->getId());
}

function orderDataQuote(): Mage_Sales_Model_Quote
{
    $quote = Mage::getService('checkout/cart')->create(null, 1);
    $created = &orderDataCreated();
    $created['quotes'][] = (int) $quote->getId();

    return $quote;
}

function orderDataSampleProduct(string $type): Mage_Catalog_Model_Product
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', $type)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addWebsiteFilter(1)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem();
    if (!$product->getId()) {
        test()->markTestSkipped("No enabled {$type} product in the sample data");
    }

    return Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
}

/**
 * A simple product that manages no stock, so the orders of this file leave the sample data unchanged.
 *
 * @param array<string, mixed> $data
 */
function orderDataProduct(array $data = []): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-order-data-' . uniqid())->setName('Pest order data product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->addData($data);
    $product->save();
    $created = &orderDataCreated();
    $created['products'][] = (int) $product->getId();

    return $product;
}

afterEach(function (): void {
    // Cancel first: a canceled order gives the stock of the sample products back.
    $created = &orderDataCreated();
    foreach ($created['orders'] as $orderId) {
        $order = Mage::getModel('sales/order')->load($orderId);
        if ($order->canCancel()) {
            $order->cancel()->save();
        }
        // Neither purchased links nor gift messages are deleted with the order
        foreach (Mage::getModel('downloadable/link_purchased')->getCollection()->addFieldToFilter('order_id', $orderId) as $purchased) {
            $purchased->delete();
        }
        $messageIds = array_map(fn($item) => $item->getGiftMessageId(), $order->getAllItems());
        foreach (array_filter([$order->getGiftMessageId(), ...$messageIds]) as $messageId) {
            Mage::getModel('giftmessage/message')->load($messageId)->delete();
        }
        $order->delete();
    }
    foreach ($created['quotes'] as $quoteId) {
        Mage::getModel('sales/quote')->setStoreId(1)->load($quoteId)->delete();
    }
    foreach ($created['products'] as $productId) {
        Mage::getModel('catalog/product')->load($productId)->delete();
    }
    $mount = Mage::getStorage('custom_options');
    foreach ($created['files'] as $path) {
        if ($mount->fileExists($path)) {
            $mount->delete($path);
        }
    }
    $created = ['orders' => [], 'quotes' => [], 'products' => [], 'files' => []];
});

it('copies the gift messages of the cart and of a cart line to the order', function (): void {
    $store = Mage::app()->getStore(1);
    $store->setConfig(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER, '1');
    $store->setConfig(Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS, '1');

    $cart = Mage::getService('checkout/cart');
    $quote = $cart->addItem(orderDataQuote(), orderDataProduct()->getSku(), 1);
    $itemId = (int) $quote->getAllVisibleItems()[0]->getId();
    $cart->setGiftMessage($quote, null, 'Alice', 'Bob', 'Order message');
    $cart->setGiftMessage($quote, $itemId, 'Carol', 'Dave', 'Line message');

    $order = orderDataPlace($quote);

    expect(Mage::getModel('giftmessage/message')->load($order->getGiftMessageId())->getMessage())->toBe('Order message')
        ->and(Mage::getModel('giftmessage/message')->load($order->getAllVisibleItems()[0]->getGiftMessageId())->getMessage())->toBe('Line message');
});

it('stores the selection attributes on each child line of a bundle', function (): void {
    $bundle = orderDataSampleProduct(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);
    $selections = [];
    $typeInstance = $bundle->getTypeInstance(true);
    foreach ($typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($bundle), $bundle) as $selection) {
        if ($selection->isSalable()) {
            $selections[$selection->getOptionId()] ??= (int) $selection->getSelectionId();
        }
    }

    $quote = Mage::getService('checkout/cart')->addItem(orderDataQuote(), $bundle->getSku(), 1, ['bundle_option' => $selections]);
    $order = orderDataPlace($quote);

    $children = array_filter($order->getAllItems(), fn(Mage_Sales_Model_Order_Item $item) => $item->getParentItemId() !== null);
    expect($children)->not->toBeEmpty();
    foreach ($children as $child) {
        expect($child->getProductOptions())->toHaveKey('bundle_selection_attributes');
    }
});

it('creates the purchased links of a downloadable line', function (): void {
    $product = orderDataSampleProduct(Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);
    $linkIds = array_keys($product->getTypeInstance(true)->getLinks($product));

    $quote = Mage::getService('checkout/cart')->addItem(orderDataQuote(), $product->getSku(), 1, ['links' => $linkIds]);
    $order = orderDataPlace($quote);

    $item = $order->getAllVisibleItems()[0];
    $countLinks = fn() => [
        Mage::getModel('downloadable/link_purchased')->getCollection()->addFieldToFilter('order_item_id', $item->getId())->getSize(),
        Mage::getModel('downloadable/link_purchased_item')->getCollection()->addFieldToFilter('order_item_id', $item->getId())->getSize(),
    ];
    expect($countLinks())->toBe([1, count($linkIds)]);

    // The listeners run on every later save of the order, so they must not add links twice
    $item->setDataChanges(true)->save();
    $order->setDataChanges(true)->save();
    expect($countLinks())->toBe([1, count($linkIds)]);
});

it('copies an uploaded file option to the order directory', function (): void {
    $product = orderDataProduct([
        'has_options' => true, 'required_options' => true, 'can_save_custom_options' => true,
        'product_options' => [['title' => 'Upload', 'type' => 'file', 'is_require' => 1, 'price' => 0,
            'price_type' => 'fixed', 'file_extension' => 'png', 'sort_order' => 0]],
    ]);
    $optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
        ->addFieldToFilter('product_id', $product->getId())->getFirstItem()->getId();

    $image = imagecreatetruecolor(2, 2);
    imagesetpixel($image, 0, 0, random_int(0, 0xFFFFFF));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    $quote = Mage::getService('checkout/cart')->addItem(orderDataQuote(), $product->getSku(), 1, [
        'options_files' => [(string) $optionId => ['name' => 'pixel.png', 'base64_encoded_data' => base64_encode($png)]],
    ]);
    $order = orderDataPlace($quote);

    $fileOption = Mage::getModel('catalog/product_option_type_file');
    $value = null;
    foreach ($order->getAllVisibleItems()[0]->getProductOptions()['options'] ?? [] as $option) {
        if ($option['option_type'] === 'file') {
            $value = Mage::helper('core/unserializeArray')->unserialize($option['option_value']);
        }
    }
    expect($value)->toBeArray();

    $orderPath = (string) $fileOption->resolveStoragePath($value, 'order_path');
    $created = &orderDataCreated();
    array_push($created['files'], $orderPath, (string) $fileOption->resolveStoragePath($value, 'quote_path'));

    expect(Mage::getStorage('custom_options')->fileExists($orderPath))->toBeTrue();
});
