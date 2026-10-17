<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function quoteFilePng(): string
{
    $image = imagecreatetruecolor(2, 2);
    imagesetpixel($image, 0, 0, random_int(0, 0xFFFFFF));
    imagesetpixel($image, 1, 1, random_int(0, 0xFFFFFF));
    ob_start();
    imagepng($image);
    return (string) ob_get_clean();
}

function quoteFileCart(Mage_Catalog_Model_Product $product, int $optionId, string $png): Mage_Sales_Model_Quote
{
    $cart = Mage::getService('checkout/cart');
    return $cart->addItem($cart->create(null, 1), (string) $product->getSku(), 1, [
        'options_files' => [(string) $optionId => ['name' => 'pixel.png', 'base64_encoded_data' => base64_encode($png)]],
    ]);
}

function quoteFileItem(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote_Item
{
    return $quote->getAllVisibleItems()[0];
}

function quoteFilePath(Mage_Sales_Model_Quote $quote, int $optionId, string $key = 'quote_path'): string
{
    $value = Mage::helper('core/string')->unserialize(
        quoteFileItem($quote)->getOptionByCode('option_' . $optionId)->getValue(),
    );
    return (string) Mage::getModel('catalog/product_option_type_file')->resolveStoragePath($value, $key);
}

beforeEach(function (): void {
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-quote-file-' . uniqid())->setName('Pest quote file product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
        ->setHasOptions(true)->setRequiredOptions(true)->setCanSaveCustomOptions(true)
        ->setProductOptions([['title' => 'Upload', 'type' => 'file', 'is_require' => 1, 'price' => 0,
            'price_type' => 'fixed', 'file_extension' => 'png', 'sort_order' => 0]]);
    $product->save();
    $this->product = $product;
    $this->optionId = (int) Mage::getModel('catalog/product_option')->getCollection()
        ->addFieldToFilter('product_id', $product->getId())->getFirstItem()->getId();
    $this->png = quoteFilePng();
    $this->mount = Mage::getStorage('custom_options');
    $this->quoteIds = [];
    $this->orderIds = [];
    $this->files = [];
});

afterEach(function (): void {
    foreach ($this->orderIds as $orderId) {
        Mage::getModel('sales/order')->load($orderId)->delete();
    }
    foreach ($this->quoteIds as $quoteId) {
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
        if ($quote->getId()) {
            $quote->delete();
        }
    }
    foreach ($this->files as $path) {
        if ($this->mount->fileExists($path)) {
            $this->mount->delete($path);
        }
    }
    $product = Mage::getModel('catalog/product')->load($this->product->getId());
    if ($product->getId()) {
        $product->delete();
    }
});

function quoteFileSweep(int $minAge = 0): int
{
    return Mage::getResourceSingleton('sales/quote')->deleteUnusedOptionFiles($minAge);
}

it('keeps the uploaded file of a deleted cart until the sweep, which deletes it when nothing else uses it', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $path = quoteFilePath($quote, $this->optionId);
    $this->files[] = $path;

    $quote->delete();

    expect($this->mount->fileExists($path))->toBeTrue();

    quoteFileSweep();

    expect($this->mount->fileExists($path))->toBeFalse();
});

it('keeps the uploaded file of a merged cart while the cart it was merged into names it', function (): void {
    $guestCart = quoteFileCart($this->product, $this->optionId, $this->png);
    $customerCart = Mage::getService('checkout/cart')->create(null, 1);
    $this->quoteIds[] = (int) $customerCart->getId();
    $customerCart->merge($guestCart)->collectTotals()->save();
    $path = quoteFilePath($guestCart, $this->optionId);
    $this->files[] = $path;
    expect(quoteFilePath($customerCart, $this->optionId))->toBe($path);

    $guestCart->delete();
    quoteFileSweep();

    expect($this->mount->fileExists($path))->toBeTrue();

    Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $customerCart->getId())->delete();
    quoteFileSweep();

    expect($this->mount->fileExists($path))->toBeFalse();
});

it('keeps the uploaded file of a removed cart item that another cart uses', function (): void {
    $firstCart = quoteFileCart($this->product, $this->optionId, $this->png);
    $secondCart = quoteFileCart($this->product, $this->optionId, $this->png);
    $this->quoteIds[] = (int) $firstCart->getId();
    $this->quoteIds[] = (int) $secondCart->getId();
    $path = quoteFilePath($firstCart, $this->optionId);
    $this->files[] = $path;
    expect(quoteFilePath($secondCart, $this->optionId))->toBe($path);

    Mage::getService('checkout/cart')->removeItem($firstCart, (int) quoteFileItem($firstCart)->getId());

    expect($this->mount->fileExists($path))->toBeTrue();

    quoteFileSweep();

    expect($this->mount->fileExists($path))->toBeTrue();
});

it('keeps an uploaded file that a wishlist item names', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $path = quoteFilePath($quote, $this->optionId);
    $this->files[] = $path;
    $value = quoteFileItem($quote)->getOptionByCode('option_' . $this->optionId)->getValue();

    $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->setFirstname('Pest')->setLastname('Wishlist')
        ->setEmail('pest-quote-file-' . uniqid() . '@example.com');
    $customer->save();
    $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customer, true);
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->insert($resource->getTableName('wishlist/item'), [
        'wishlist_id' => (int) $wishlist->getId(),
        'product_id' => (int) $this->product->getId(),
        'store_id' => 1,
        'qty' => 1,
    ]);
    $wishlistItemId = (int) $write->lastInsertId();
    $write->insert($resource->getTableName('wishlist/item_option'), [
        'wishlist_item_id' => $wishlistItemId,
        'product_id' => (int) $this->product->getId(),
        'code' => 'option_' . $this->optionId,
        'value' => $value,
    ]);

    try {
        $quote->delete();
        quoteFileSweep();

        expect($this->mount->fileExists($path))->toBeTrue();
    } finally {
        $customer->delete();
    }
});

it('keeps an unused uploaded file that is younger than the minimum age', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $path = quoteFilePath($quote, $this->optionId);
    $this->files[] = $path;

    $quote->delete();
    quoteFileSweep(86400);

    expect($this->mount->fileExists($path))->toBeTrue();
});

it('copies the uploaded file of a deleted cart to an order that has no copy', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $quoteItem = quoteFileItem($quote);
    $quotePath = quoteFilePath($quote, $this->optionId);
    $orderPath = quoteFilePath($quote, $this->optionId, 'order_path');
    $this->files[] = $quotePath;
    $this->files[] = $orderPath;

    $order = Mage::getModel('sales/order')->setStoreId(1)->setQuoteId((int) $quote->getId())
        ->setState(Mage_Sales_Model_Order::STATE_NEW)->setStatus('pending')
        ->setGrandTotal(10)->setBaseGrandTotal(10);
    $orderItem = Mage::getModel('sales/order_item')->setProductType('simple')
        ->setProductId((int) $this->product->getId())->setQuoteItemId((int) $quoteItem->getId())
        ->setSku((string) $this->product->getSku())->setName('Pest quote file product')
        ->setQtyOrdered(1)->setPrice(10)->setBasePrice(10)->setRowTotal(10)->setBaseRowTotal(10)
        ->setProductOptions(['options' => [[
            'label' => 'Upload',
            'option_id' => $this->optionId,
            'option_type' => 'file',
            'option_value' => $quoteItem->getOptionByCode('option_' . $this->optionId)->getValue(),
        ]]]);
    $order->addItem($orderItem);
    $order->save();
    $this->orderIds[] = (int) $order->getId();
    expect($this->mount->fileExists($orderPath))->toBeFalse();

    $quote->delete();

    expect($this->mount->fileExists($orderPath))->toBeTrue();

    quoteFileSweep();

    expect($this->mount->fileExists($orderPath))->toBeTrue()
        ->and($this->mount->fileExists($quotePath))->toBeFalse();
});

it('gives an order that has only the quote copy of its file an order copy on upgrade', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $this->quoteIds[] = (int) $quote->getId();
    $quoteItem = quoteFileItem($quote);
    $orderPath = quoteFilePath($quote, $this->optionId, 'order_path');
    $this->files[] = quoteFilePath($quote, $this->optionId);
    $this->files[] = $orderPath;

    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_NEW)->setStatus('pending')
        ->setGrandTotal(10)->setBaseGrandTotal(10);
    $order->addItem(Mage::getModel('sales/order_item')->setProductType('simple')
        ->setProductId((int) $this->product->getId())
        ->setSku((string) $this->product->getSku())->setName('Pest quote file product')
        ->setQtyOrdered(1)->setPrice(10)->setBasePrice(10)->setRowTotal(10)->setBaseRowTotal(10)
        ->setProductOptions(['options' => [[
            'label' => 'Upload',
            'option_id' => $this->optionId,
            'option_type' => 'file',
            'option_value' => $quoteItem->getOptionByCode('option_' . $this->optionId)->getValue(),
        ]]]));
    $order->save();
    $this->orderIds[] = (int) $order->getId();
    expect($this->mount->fileExists($orderPath))->toBeFalse();

    $setup = new Mage_Sales_Model_Resource_Setup('sales_setup');
    $script = Mage::getModuleDir('', 'Mage_Sales') . '/data/sales_setup/data-upgrade-2.0.1-2.0.2.php';
    (function () use ($script): void {
        include $script;
    })->call($setup);

    expect($this->mount->fileExists($orderPath))->toBeTrue();
});

it('keeps every file while the Sales data upgrade has not run', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $path = quoteFilePath($quote, $this->optionId);
    $this->files[] = $path;
    $quote->delete();
    $resource = Mage::getResourceSingleton('core/resource');
    $resource->setDataVersion('sales_setup', '2.0.1');

    try {
        expect(quoteFileSweep())->toBe(0)
            ->and($this->mount->fileExists($path))->toBeTrue();
    } finally {
        $resource->setDataVersion('sales_setup', '2.0.2');
    }
});

it('gives an order its own copy of the file when it is placed, so a product delete keeps it', function (): void {
    $quote = quoteFileCart($this->product, $this->optionId, $this->png);
    $this->quoteIds[] = (int) $quote->getId();
    $quotePath = quoteFilePath($quote, $this->optionId);
    $orderPath = quoteFilePath($quote, $this->optionId, 'order_path');
    $this->files[] = $quotePath;
    $this->files[] = $orderPath;
    $address = [
        'firstname' => 'Pest', 'lastname' => 'Order', 'street' => '1 Test Street', 'city' => 'Los Angeles',
        'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100',
    ];
    $quote->getBillingAddress()->addData($address);
    $quote->getShippingAddress()->addData($address)->setCollectShippingRates(true)->collectShippingRates()
        ->setShippingMethod('flatrate_flatrate');
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

    $order = Mage::getService('sales/order')->place($quote, 'pest-quote-file@example.com')['order'];
    $this->orderIds[] = (int) $order->getId();

    expect($this->mount->fileExists($orderPath))->toBeTrue();

    // The foreign keys delete the quote items of the product without any model hook
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName('catalog/product'), ['entity_id = ?' => (int) $this->product->getId()]);
    quoteFileSweep();

    expect($this->mount->fileExists($orderPath))->toBeTrue();
});
