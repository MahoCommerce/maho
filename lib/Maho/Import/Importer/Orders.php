<?php

/**
 * Sales orders placed through the cart service and the order service, keyed by reference, dated back from now by hours_ago.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

/**
 * @phpstan-type StockColumns array{qty: float, is_in_stock: int, low_stock_date: ?string, stock_status_changed_auto: int}
 */
class Orders extends AbstractImporter
{
    /** Skip the refresh of the report statistics, for a caller that refreshes them once after several files. */
    public const OPTION_SKIP_STATISTICS = 'skip_statistics';

    /** The report statistics that read the orders and their documents. */
    public const STATISTICS = ['sales', 'tax', 'shipping', 'invoiced', 'refunded', 'coupons', 'bestsellers'];

    /** What each status of the file does to the order after it is placed. */
    private const STATUSES = ['pending', 'processing', 'complete', 'closed', 'canceled'];

    /** @var array<string, array{id: int, parent_id: int|null, website_ids: list<int>}> */
    private array $products = [];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['reference', 'store_code', 'hours_ago', 'status', 'email', 'firstname', 'lastname',
            'street', 'city', 'postcode', 'country_id', 'telephone', 'items'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        $references = [];
        foreach ($file as $line => $row) {
            foreach ($this->requiredColumns() as $column) {
                $this->requireValue($file, $line, $row, $column);
            }
            if (isset($references[$row['reference']])) {
                $this->fail($file, $line, "reference '{$row['reference']}' is also on line {$references[$row['reference']]}");
            }
            $references[$row['reference']] = $line;
            $row['store_id'] = $this->at($file, $line, fn() => $this->resolver->storeId($row['store_code']));
            $websiteId = (int) Mage::app()->getStore($row['store_id'])->getWebsiteId();
            if (!ctype_digit($row['hours_ago'])) {
                $this->fail($file, $line, 'hours_ago must be a whole number');
            }
            if (!in_array($row['status'], self::STATUSES, true)) {
                $this->fail($file, $line, "status '{$row['status']}' is not one of " . implode(', ', self::STATUSES));
            }
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $this->fail($file, $line, "email '{$row['email']}' is not valid");
            }
            $row['region_id'] = $this->regionId($file, $line, $row['country_id'], $row['region'] ?? '');
            $row['lines'] = [];
            foreach (CsvFile::list($row['items']) as $item) {
                if (!preg_match('/^(.+):(\d+)$/', $item, $match) || (int) $match[2] < 1) {
                    $this->fail($file, $line, "item '$item' is not sku:qty with a qty of 1 or more");
                }
                $product = $this->product($file, $line, $match[1]);
                if (!in_array($websiteId, $product['website_ids'], true)) {
                    $this->fail($file, $line, "sku '{$match[1]}' is not in the website of store '{$row['store_code']}'");
                }
                $row['lines'][] = ['product_id' => $product['id'], 'parent_id' => $product['parent_id'], 'qty' => (int) $match[2]];
            }
            $row['payment_method'] = ($row['payment_method'] ?? '') !== '' ? $row['payment_method'] : 'checkmo';
            $row['shipping_method'] = ($row['shipping_method'] ?? '') !== '' ? $row['shipping_method'] : 'flatrate_flatrate';
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $productIds = [];
        foreach ($rows as $row) {
            foreach ($row['lines'] as $orderLine) {
                $productIds[$orderLine['product_id']] = $orderLine['product_id'];
                if ($orderLine['parent_id'] !== null) {
                    $productIds[$orderLine['parent_id']] = $orderLine['parent_id'];
                }
            }
        }
        $stock = $this->stockOf(array_values($productIds));
        $restored = [];
        $now = time();
        $done = 0;
        try {
            foreach ($rows as $line => $row) {
                $reporter->progress(++$done, count($rows), 'orders');
                if ($this->exists($row['reference'], (int) $row['store_id'])) {
                    continue;
                }
                try {
                    $order = Mage::app()->withStore((int) $row['store_id'], fn() => $this->place($row));
                    $this->applyStatus($order, $row['status']);
                } catch (\Mage_Core_Exception $e) {
                    $this->fail($file, $line, $e->getMessage());
                } finally {
                    // Every order puts the stock back, so the next orders of a popular product still find it.
                    $restored += $this->restoreStock($stock, $row['lines']);
                }
                // A few minutes from the reference, so the orders of one hour do not share a time.
                $seconds = (int) $row['hours_ago'] * 3600 + crc32($row['reference']) % 3600;
                $this->moveDates((int) $order->getId(), gmdate('Y-m-d H:i:s', $now - $seconds));
                $result->created++;
            }
        } finally {
            if ($restored !== []) {
                // An order that takes the last unit removes the product from the price, filter and search indexes
                $products = new \Maho\DataObject(['product_ids' => array_values($restored), 'attributes_data' => ['force_reindex_required' => 1]]);
                Mage::getSingleton('index/indexer')->processEntityAction($products, \Mage_Catalog_Model_Product::ENTITY, \Mage_Index_Model_Event::TYPE_MASS_ACTION);
            }
        }
        if ($result->created > 0 && !($options[self::OPTION_SKIP_STATISTICS] ?? false)) {
            Mage::getModel('reports/statistics')->refreshLifetime(self::STATISTICS);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function place(array $row): \Mage_Sales_Model_Order
    {
        $storeId = (int) $row['store_id'];
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId)->setIsActive(true);
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId((int) Mage::app()->getStore($storeId)->getWebsiteId())
            ->loadByEmail($row['email']);
        if ($customer->getId()) {
            $quote->assignCustomer($customer);
        } else {
            $quote->setCustomerIsGuest(true)
                ->setCustomerGroupId(\Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                ->setCustomerEmail($row['email'])
                ->setCustomerFirstname($row['firstname'])
                ->setCustomerLastname($row['lastname']);
        }
        try {
            return $this->submit($quote, $storeId, $row);
        } catch (\Throwable $e) {
            // A quote without an order must not become the cart of its customer
            if ($quote->getId()) {
                $quote->setIsActive(false)->save();
            }
            throw $e;
        }
    }

    /**
     * Fill $quote through the cart service, as staff, and place its order through the order service.
     *
     * @param array<string, mixed> $row
     */
    private function submit(\Mage_Sales_Model_Quote $quote, int $storeId, array $row): \Mage_Sales_Model_Order
    {
        /** @var \Mage_Checkout_Service_Cart $cart */
        $cart = Mage::getService('checkout/cart');
        foreach ($row['lines'] as $orderLine) {
            $this->addLine($cart, $quote, $storeId, $orderLine);
        }
        $address = [
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'street' => $row['street'],
            'city' => $row['city'],
            'region_id' => $row['region_id'],
            'region' => $row['region'] ?? '',
            'postcode' => $row['postcode'],
            'country_id' => $row['country_id'],
            'telephone' => $row['telephone'],
            'email' => $row['email'],
        ];
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address)
            ->setCollectShippingRates(true)
            ->setShippingMethod($row['shipping_method']);
        $quote->getPayment()->importData(['method' => $row['payment_method']]);
        $cart->collectAndSave($quote);
        if (($row['coupon_code'] ?? '') !== '') {
            $cart->applyCoupon($quote, $row['coupon_code'], \Mage_Checkout_Model_Actor::Staff);
        }

        return Mage::getService('sales/order')->submit(
            $quote,
            \Mage_Checkout_Model_Actor::Staff,
            orderData: ['ext_order_id' => $row['reference']],
            sendConfirmation: false,
        );
    }

    /**
     * Adds a line to the quote; the child of a configurable goes in through its parent, with the options that select it.
     *
     * @param array{product_id: int, parent_id: int|null, qty: int} $orderLine
     */
    private function addLine(\Mage_Checkout_Service_Cart $cart, \Mage_Sales_Model_Quote $quote, int $storeId, array $orderLine): void
    {
        $request = ['qty' => $orderLine['qty']];
        $productId = $orderLine['product_id'];
        if ($orderLine['parent_id'] !== null) {
            $parent = Mage::getModel('catalog/product')->setStoreId($storeId)->load($orderLine['parent_id']);
            /** @var \Mage_Catalog_Model_Product_Type_Configurable $type */
            $type = $parent->getTypeInstance(true);
            $request['super_attribute'] = [];
            foreach ($type->getConfigurableAttributesAsArray($parent) as $attribute) {
                $request['super_attribute'][$attribute['attribute_id']] = Mage::getResourceSingleton('catalog/product')
                    ->getAttributeRawValue($productId, $attribute['attribute_code'], $storeId);
            }
            $productId = $orderLine['parent_id'];
        }
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        $cart->addProduct($quote, $product, new \Maho\DataObject($request), \Mage_Checkout_Model_Actor::Staff, save: false);
    }

    /**
     * Move $order to $status through the order service, with no email to the customer. The service refuses a step
     * that the state of the order does not allow.
     *
     * @throws \Mage_Core_Exception
     */
    private function applyStatus(\Mage_Sales_Model_Order $order, string $status): void
    {
        if ($status === 'pending') {
            return;
        }
        if ($status === 'canceled') {
            Mage::getService('sales/order')->cancel($order);
            return;
        }
        /** @var \Mage_Sales_Service_Order_Document $documents */
        $documents = Mage::getService('sales/order_document');
        $documents->invoice($order, captureCase: \Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
        // An order of virtual products only has nothing to ship
        if ($status === 'complete' && $order->canShip()) {
            $documents->ship($order, sendEmail: false);
        }
        if ($status === 'closed') {
            $documents->refund($order, [], offlineRefund: true, sendEmail: false);
        }
    }

    private function exists(string $reference, int $storeId): bool
    {
        return (bool) Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('ext_order_id', $reference)
            ->addFieldToFilter('store_id', $storeId)
            ->getSize();
    }

    /**
     * Moves the order, its items, its history and its documents to the UTC date $date.
     */
    private function moveDates(int $orderId, string $date): void
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $dates = ['created_at' => $date, 'updated_at' => $date];
        $write->update($resource->getTableName('sales/order'), $dates, ['entity_id = ?' => $orderId]);
        $write->update($resource->getTableName('sales/order_grid'), $dates, ['entity_id = ?' => $orderId]);
        $write->update($resource->getTableName('sales/order_item'), $dates, ['order_id = ?' => $orderId]);
        $write->update($resource->getTableName('sales/order_status_history'), ['created_at' => $date], ['parent_id = ?' => $orderId]);
        foreach (['invoice', 'shipment', 'creditmemo'] as $document) {
            $write->update($resource->getTableName("sales/$document"), $dates, ['order_id = ?' => $orderId]);
            $write->update($resource->getTableName("sales/{$document}_grid"), ['created_at' => $date, 'order_created_at' => $date], ['order_id = ?' => $orderId]);
        }
    }

    /**
     * @param list<int> $productIds
     * @return array<int, StockColumns>
     */
    private function stockOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $stock = [];
        $select = $read->select()
            ->from($resource->getTableName('cataloginventory/stock_item'), ['product_id', 'qty', 'is_in_stock', 'low_stock_date', 'stock_status_changed_auto'])
            ->where('product_id IN (?)', $productIds);
        foreach ($read->fetchAll($select) as $item) {
            $stock[(int) $item['product_id']] = [
                'qty' => (float) $item['qty'],
                'is_in_stock' => (int) $item['is_in_stock'],
                'low_stock_date' => $item['low_stock_date'],
                'stock_status_changed_auto' => (int) $item['stock_status_changed_auto'],
            ];
        }
        return $stock;
    }

    /**
     * Puts the stock of the products of an order back as it was before the import: placing an order
     * takes stock, and cancelling one gives it back. A save of the stock item would reindex each product
     * and clean its cache, so the update writes the stock columns back and reindexes the stock status only.
     *
     * @param array<int, StockColumns> $stock
     * @param list<array{product_id: int, parent_id: int|null, qty: int}> $lines
     * @return array<int, int> the products that got their stock back, by ID
     */
    private function restoreStock(array $stock, array $lines): array
    {
        $productIds = [];
        foreach ($lines as $orderLine) {
            $productIds[] = $orderLine['product_id'];
            if ($orderLine['parent_id'] !== null) {
                $productIds[] = $orderLine['parent_id'];
            }
        }
        $restored = [];
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        foreach ($this->stockOf(array_values(array_unique($productIds))) as $productId => $current) {
            $was = $stock[$productId] ?? null;
            if ($was === null || $current === $was) {
                continue;
            }
            $write->update($resource->getTableName('cataloginventory/stock_item'), $was, ['product_id = ?' => $productId]);
            $restored[$productId] = $productId;
        }
        if ($restored !== []) {
            Mage::getResourceSingleton('cataloginventory/indexer_stock')->reindexProducts(array_values($restored));
        }
        return $restored;
    }

    private function regionId(CsvFile $file, int $line, string $countryId, string $region): ?int
    {
        if ($region !== '') {
            $regionId = Mage::getModel('directory/region')->loadByCodeOrName($region, $countryId)->getId();
            if ($regionId) {
                return (int) $regionId;
            }
        }
        if (Mage::helper('directory')->isRegionRequired($countryId)) {
            $this->fail($file, $line, "region '$region' is not a region of $countryId, and $countryId needs one");
        }
        return null;
    }

    /**
     * @return array{id: int, parent_id: int|null, website_ids: list<int>}
     */
    private function product(CsvFile $file, int $line, string $sku): array
    {
        if (isset($this->products[$sku])) {
            return $this->products[$sku];
        }
        $id = (int) Mage::getModel('catalog/product')->getIdBySku($sku);
        if ($id === 0) {
            $this->fail($file, $line, "unknown sku '$sku'");
        }
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $type = (string) $read->fetchOne(
            $read->select()->from($resource->getTableName('catalog/product'), 'type_id')->where('entity_id = ?', $id),
        );
        if (!in_array($type, [\Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, \Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL], true)) {
            $this->fail($file, $line, "sku '$sku' is a $type product: name a simple or virtual sku, such as a variant of a configurable");
        }
        $parentId = $read->fetchOne(
            $read->select()->from($resource->getTableName('catalog/product_super_link'), 'parent_id')->where('product_id = ?', $id)->limit(1),
        );
        $websiteIds = $read->fetchCol(
            $read->select()->from($resource->getTableName('catalog/product_website'), 'website_id')->where('product_id = ?', $id),
        );
        return $this->products[$sku] = [
            'id' => $id,
            'parent_id' => $parentId === false ? null : (int) $parentId,
            'website_ids' => array_map(intval(...), $websiteIds),
        ];
    }
}
