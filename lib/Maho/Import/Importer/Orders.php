<?php

/**
 * Sales orders placed through the quote service, keyed by reference, dated back from now by hours_ago.
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
        $now = time();
        $done = 0;
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
                $this->restoreStock($stock, $row['lines']);
            }
            // A few minutes from the reference, so the orders of one hour do not share a time.
            $seconds = (int) $row['hours_ago'] * 3600 + crc32($row['reference']) % 3600;
            $this->moveDates((int) $order->getId(), gmdate('Y-m-d H:i:s', $now - $seconds));
            $result->created++;
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
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId)->setIsActive(false);
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
        foreach ($row['lines'] as $orderLine) {
            $this->addLine($quote, $storeId, $orderLine);
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
        if (($row['coupon_code'] ?? '') !== '') {
            $quote->setCouponCode($row['coupon_code']);
        }
        $quote->collectTotals()->save();

        $service = new \Mage_Sales_Model_Service_Quote($quote);
        $service->setOrderData(['ext_order_id' => $row['reference']]);
        $service->submitAll();
        $order = $service->getOrder();
        if (!$order instanceof \Mage_Sales_Model_Order || !$order->getId()) {
            throw new \Mage_Core_Exception('the quote service placed no order');
        }
        return $order;
    }

    /**
     * Adds a line to the quote; the child of a configurable goes in through its parent, with the options that select it.
     *
     * @param array{product_id: int, parent_id: int|null, qty: int} $orderLine
     */
    private function addLine(\Mage_Sales_Model_Quote $quote, int $storeId, array $orderLine): void
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
        $item = $quote->addProduct($product, new \Maho\DataObject($request));
        if (is_string($item)) {
            throw new \Mage_Core_Exception("sku '{$product->getSku()}': $item");
        }
    }

    private function applyStatus(\Mage_Sales_Model_Order $order, string $status): void
    {
        if ($status === 'pending') {
            return;
        }
        if ($status === 'canceled') {
            $order->cancel()->save();
            return;
        }
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)->register();
        $order->setIsInProcess(true);
        Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();
        if ($status === 'complete' && $order->canShip()) {
            $shipment = $order->prepareShipment();
            $shipment->register();
            // The order must carry a change of its own, or its save skips the shipped items too.
            $order->setIsInProcess(true);
            Mage::getModel('core/resource_transaction')->addObject($shipment)->addObject($order)->save();
        }
        if ($status === 'closed') {
            $creditmemo = Mage::getModel('sales/service_order', $order)->prepareCreditmemo();
            $creditmemo->setOfflineRequested(true)->register();
            $order->setIsInProcess(true);
            Mage::getModel('core/resource_transaction')->addObject($creditmemo)->addObject($order)->save();
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
     * @return array<int, array{qty: float, is_in_stock: bool}>
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
            ->from($resource->getTableName('cataloginventory/stock_item'), ['product_id', 'qty', 'is_in_stock'])
            ->where('product_id IN (?)', $productIds);
        foreach ($read->fetchAll($select) as $item) {
            $stock[(int) $item['product_id']] = ['qty' => (float) $item['qty'], 'is_in_stock' => (bool) $item['is_in_stock']];
        }
        return $stock;
    }

    /**
     * Puts the stock of the products of an order back as it was before the import: placing an order
     * takes stock, and cancelling one gives it back.
     *
     * @param array<int, array{qty: float, is_in_stock: bool}> $stock
     * @param list<array{product_id: int, parent_id: int|null, qty: int}> $lines
     */
    private function restoreStock(array $stock, array $lines): void
    {
        $productIds = [];
        foreach ($lines as $orderLine) {
            $productIds[] = $orderLine['product_id'];
            if ($orderLine['parent_id'] !== null) {
                $productIds[] = $orderLine['parent_id'];
            }
        }
        foreach (array_unique($productIds) as $productId) {
            if (!isset($stock[$productId])) {
                continue;
            }
            $was = $stock[$productId];
            $item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
            if ((float) $item->getQty() === $was['qty'] && (bool) $item->getIsInStock() === $was['is_in_stock']) {
                continue;
            }
            $item->setQty($was['qty'])->setIsInStock($was['is_in_stock'])->save();
        }
    }

    private function regionId(CsvFile $file, int $line, string $countryId, string $region): ?int
    {
        if ($region !== '') {
            $model = Mage::getModel('directory/region')->loadByCode($region, $countryId);
            if (!$model->getId()) {
                $model = Mage::getModel('directory/region')->loadByName($region, $countryId);
            }
            if ($model->getId()) {
                return (int) $model->getId();
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
