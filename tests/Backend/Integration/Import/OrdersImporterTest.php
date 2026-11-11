<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Orders;
use Maho\Import\RowException;
use Tests\Helpers\ReportFixture;

uses(Tests\MahoBackendTestCase::class);

const ORDERS_HEADER = ['reference', 'store_code', 'hours_ago', 'status', 'email', 'firstname', 'lastname',
    'street', 'city', 'region', 'postcode', 'country_id', 'telephone', 'items'];

/**
 * @param list<list<string>> $rows
 * @param list<string> $extraColumns columns after the columns of ORDERS_HEADER
 */
function ordersCsv(array $rows, array $extraColumns = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'orders') . '.csv';
    $handle = fopen($path, 'w');
    foreach ([[...ORDERS_HEADER, ...$extraColumns], ...$rows] as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

/**
 * @return list<string>
 */
function ordersRow(string $reference, string $status, int $hoursAgo, string $email, string $items): array
{
    $store = Mage::app()->getStore(1)->getCode();
    return [$reference, $store, (string) $hoursAgo, $status, $email, 'Imp', 'Buyer',
        '1 Import Street', 'Los Angeles', 'California', '90028', 'US', '555-0100', $items];
}

function importedOrder(string $reference): Mage_Sales_Model_Order
{
    $id = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', $reference)->getFirstItem()->getId();
    return Mage::getModel('sales/order')->load($id);
}

function stockQty(int $productId): float
{
    return (float) Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId)->getQty();
}

function ordersCleanup(): void
{
    $orders = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', ['like' => 'IMP-ORD-%']);
    $quoteIds = array_filter(array_map(intval(...), $orders->getColumnValues('quote_id')));
    ReportFixture::deleteOrders(array_map(intval(...), $orders->getColumnValues('entity_id')));
    foreach ($quoteIds as $quoteId) {
        Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId)->delete();
    }
    foreach (Mage::getResourceModel('customer/customer_collection')->addFieldToFilter('email', 'imp.orders@example.com') as $customer) {
        $customer->delete();
    }
}

beforeEach(fn() => ordersCleanup());
afterEach(fn() => ordersCleanup());

it('places an order for each status, dates it back and skips it on rerun', function (): void {
    $product = loadSimplePricedProduct();
    $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->setStoreId(1)->setGroupId(1)
        ->setEmail('imp.orders@example.com')->setFirstname('Imp')->setLastname('Buyer')->save();
    $stock = stockQty((int) $product->getId());
    $item = $product->getSku() . ':1';
    $path = ordersCsv([
        ordersRow('IMP-ORD-1', 'complete', 30, 'imp.orders@example.com', $item),
        ordersRow('IMP-ORD-2', 'closed', 2, 'imp.guest@example.org', $item),
        ordersRow('IMP-ORD-3', 'canceled', 5, 'imp.guest@example.org', $item),
        ordersRow('IMP-ORD-4', 'pending', 0, 'imp.guest@example.org', $item),
        ordersRow('IMP-ORD-5', 'processing', 50, 'imp.orders@example.com', $item),
    ]);

    $result = (new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true]);
    expect($result->created)->toBe(5);

    $complete = importedOrder('IMP-ORD-1');
    expect($complete->getState())->toBe(Mage_Sales_Model_Order::STATE_COMPLETE);
    expect((int) $complete->getCustomerId())->toBe((int) $customer->getId());
    expect((float) $complete->getBaseTotalInvoiced())->toBeGreaterThan(0.0);
    expect($complete->getShipmentsCollection()->count())->toBe(1);
    $age = time() - strtotime($complete->getCreatedAt() . ' UTC');
    expect($age)->toBeGreaterThanOrEqual(30 * 3600)->toBeLessThan(31 * 3600 + 60);

    $closed = importedOrder('IMP-ORD-2');
    expect($closed->getState())->toBe(Mage_Sales_Model_Order::STATE_CLOSED);
    expect((bool) $closed->getCustomerIsGuest())->toBeTrue();
    expect((float) $closed->getBaseTotalRefunded())->toBe((float) $closed->getBaseTotalInvoiced());
    expect(importedOrder('IMP-ORD-3')->getState())->toBe(Mage_Sales_Model_Order::STATE_CANCELED);
    expect(importedOrder('IMP-ORD-4')->getState())->toBe(Mage_Sales_Model_Order::STATE_NEW);
    $processing = importedOrder('IMP-ORD-5');
    expect($processing->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
    expect($processing->getInvoiceCollection()->count())->toBe(1);
    expect($processing->getShipmentsCollection()->count())->toBe(0);

    expect(stockQty((int) $product->getId()))->toBe($stock);

    $again = (new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true]);
    expect($again->created)->toBe(0);
    expect(Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', ['like' => 'IMP-ORD-%'])->getSize())->toBe(5);
    unlink($path);
});

it('orders the variant of a configurable through its parent', function (): void {
    $parents = Mage::getResourceModel('catalog/product_collection')
        ->addWebsiteFilter([1])
        ->addAttributeToFilter('type_id', 'configurable')
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToSort('entity_id', 'ASC');
    $variant = null;
    foreach ($parents as $candidate) {
        $parent = Mage::getModel('catalog/product')->load($candidate->getId());
        foreach ($parent->getTypeInstance(true)->getUsedProducts(null, $parent) as $child) {
            if ($child->isSaleable() && Mage::getModel('cataloginventory/stock_item')->loadByProduct($child)->getIsInStock()) {
                $variant = $child;
                break 2;
            }
        }
    }
    if ($variant === null) {
        $this->markTestSkipped('The test database has no configurable product with a variant in stock');
    }
    $path = ordersCsv([ordersRow('IMP-ORD-6', 'processing', 1, 'imp.guest@example.org', $variant->getSku() . ':2')]);

    expect((new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true])->created)->toBe(1);

    $items = importedOrder('IMP-ORD-6')->getAllItems();
    $types = array_map(fn($item) => $item->getProductType(), $items);
    sort($types);
    expect($types)->toBe(['configurable', 'simple']);
    foreach ($items as $item) {
        expect($item->getSku())->toBe($variant->getSku());
        expect((float) $item->getQtyOrdered())->toBe(2.0);
    }
    unlink($path);
});

it('rejects an unknown sku, a bad item, a bad status and a missing region before writing', function (): void {
    $product = loadSimplePricedProduct();
    $importer = new Orders();
    $item = $product->getSku() . ':1';

    $path = ordersCsv([ordersRow('IMP-ORD-7', 'complete', 1, 'imp.guest@example.org', 'NO-SUCH-SKU:1')]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "line 2: unknown sku 'NO-SUCH-SKU'");

    $path = ordersCsv([ordersRow('IMP-ORD-7', 'complete', 1, 'imp.guest@example.org', $product->getSku())]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'is not sku:qty');

    $path = ordersCsv([ordersRow('IMP-ORD-7', 'shipped', 1, 'imp.guest@example.org', $item)]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "status 'shipped' is not one of");

    $row = ordersRow('IMP-ORD-7', 'complete', 1, 'imp.guest@example.org', $item);
    $row[9] = 'Atlantis';
    $path = ordersCsv([$row]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "region 'Atlantis' is not a region of US");

    $path = ordersCsv([
        ordersRow('IMP-ORD-7', 'complete', 1, 'imp.guest@example.org', $item),
        ordersRow('IMP-ORD-7', 'complete', 1, 'imp.guest@example.org', $item),
    ]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "line 3: reference 'IMP-ORD-7' is also on line 2");
    expect(Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', ['like' => 'IMP-ORD-%'])->getSize())->toBe(0);
});

it('reports a row with a coupon code that does not apply as failed, and places no order for it', function (): void {
    $item = loadSimplePricedProduct()->getSku() . ':1';
    $path = ordersCsv([[...ordersRow('IMP-ORD-8', 'processing', 1, 'imp.guest@example.org', $item), 'NO-SUCH-COUPON']], ['coupon_code']);

    expect(fn() => (new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true]))
        ->toThrow(RowException::class, "Coupon code 'NO-SUCH-COUPON' is not valid");
    expect(Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', 'IMP-ORD-8')->getSize())->toBe(0);
    unlink($path);
});

it('reports a row with a disabled product as failed, and places no order for it', function (): void {
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-import-order-' . uniqid())->setName('Pest import order product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
    $product->save();
    $path = ordersCsv([ordersRow('IMP-ORD-9', 'processing', 1, 'imp.guest@example.org', $product->getSku() . ':1')]);

    try {
        expect(fn() => (new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true]))
            ->toThrow(RowException::class, "Product '{$product->getSku()}' is not available");
        expect(Mage::getResourceModel('sales/order_collection')->addFieldToFilter('ext_order_id', 'IMP-ORD-9')->getSize())->toBe(0);
    } finally {
        $product->delete();
        unlink($path);
    }
});

it('moves an order to its status through the order services, with no email to the customer', function (): void {
    $calls = new ArrayObject();
    $orders = new class ($calls) extends Mage_Sales_Service_Order {
        public function __construct(private ArrayObject $calls) {}

        #[\Override]
        public function cancel(Mage_Sales_Model_Order $order, ?string $reason = null): Mage_Sales_Model_Order
        {
            $this->calls[] = 'cancel';
            return parent::cancel($order, $reason);
        }
    };
    $documents = new class ($calls) extends Mage_Sales_Service_Order_Document {
        public function __construct(private ArrayObject $calls) {}

        #[\Override]
        public function invoice(
            Mage_Sales_Model_Order $order,
            array $qtys = [],
            ?string $captureCase = null,
            ?string $comment = null,
            bool $notifyCustomer = false,
            bool $visibleOnFront = false,
            ?bool $sendEmail = null,
            bool $ship = false,
            array $tracks = [],
        ): Mage_Sales_Model_Order_Invoice {
            $this->calls[] = 'invoice:' . $captureCase . ':' . var_export($sendEmail, true);
            return parent::invoice($order, $qtys, $captureCase, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $ship, $tracks);
        }
    };
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', $orders);
    Mage::unregister('_service/sales/order_document');
    Mage::register('_service/sales/order_document', $documents);
    $item = loadSimplePricedProduct()->getSku() . ':1';
    $path = ordersCsv([
        ordersRow('IMP-ORD-10', 'canceled', 5, 'imp.guest@example.org', $item),
        ordersRow('IMP-ORD-11', 'processing', 5, 'imp.guest@example.org', $item),
    ]);

    try {
        (new Orders())->import($path, [Orders::OPTION_SKIP_STATISTICS => true]);
    } finally {
        Mage::unregister('_service/sales/order');
        Mage::unregister('_service/sales/order_document');
        unlink($path);
    }

    expect($calls->getArrayCopy())->toBe(['cancel', 'invoice:offline:false']);
});
