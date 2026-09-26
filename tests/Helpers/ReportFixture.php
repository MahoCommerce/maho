<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Fixtures of the report API tests: products, orders on a fixed day far in the past, the aggregation of
 * that day, and a snapshot of the report tables that restores the state of the store after the test.
 */
final class ReportFixture
{
    /**
     * The tables that the refresh of the statistics writes.
     */
    public const AGGREGATED_TABLES = [
        'coupon_aggregated', 'coupon_aggregated_order', 'coupon_aggregated_updated',
        'report_viewed_product_aggregated_daily', 'report_viewed_product_aggregated_monthly', 'report_viewed_product_aggregated_yearly',
        'sales_bestsellers_aggregated_daily', 'sales_bestsellers_aggregated_monthly', 'sales_bestsellers_aggregated_yearly',
        'sales_invoiced_aggregated', 'sales_invoiced_aggregated_order',
        'sales_order_aggregated_created', 'sales_order_aggregated_updated',
        'sales_refunded_aggregated', 'sales_refunded_aggregated_order',
        'sales_shipping_aggregated', 'sales_shipping_aggregated_order',
        'tax_order_aggregated_created', 'tax_order_aggregated_updated',
    ];

    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $snapshot = null;
    private static int $lastQueueMessageId = 0;

    /** @var list<int> */
    private static array $orderIds = [];
    /** @var list<int> */
    private static array $productIds = [];
    /** @var list<int> */
    private static array $customerIds = [];
    /** @var list<int> */
    private static array $quoteIds = [];
    /** @var list<int> */
    private static array $ruleIds = [];
    /** @var list<int> */
    private static array $eventIds = [];
    /** @var list<int> */
    private static array $searchQueryIds = [];
    /** @var list<int> */
    private static array $visitorIds = [];
    /** @var list<int> */
    private static array $urlIds = [];

    public static function adapter(): \Maho\Db\Adapter\AdapterInterface
    {
        ApiV2Helper::ensureMahoBootstrapped();
        return \Mage::getSingleton('core/resource')->getConnection('core_write');
    }

    public static function table(string $name): string
    {
        return \Mage::getSingleton('core/resource')->getTableName($name);
    }

    /**
     * Save the report tables, the report flags, the order and invoice increment counters and the
     * last queue message. restore() puts them back.
     */
    public static function snapshot(): void
    {
        $adapter = self::adapter();
        $snapshot = [];
        foreach (self::AGGREGATED_TABLES as $table) {
            $snapshot[$table] = $adapter->fetchAll($adapter->select()->from(self::table($table)));
        }
        $snapshot['core_flag'] = $adapter->fetchAll(
            $adapter->select()->from(self::table('core/flag'))->where('flag_code IN (?)', self::flagCodes()),
        );
        $snapshot['eav_entity_store'] = $adapter->fetchAll($adapter->select()->from(self::table('eav/entity_store')));
        self::$snapshot = $snapshot;
        self::$lastQueueMessageId = (int) $adapter->fetchOne(
            $adapter->select()->from(\Maho\Queue\QueueManager::tableName(), [new \Maho\Db\Expr('MAX(message_id)')]),
        );
    }

    /**
     * Delete the fixtures and put back the state of snapshot().
     */
    public static function restore(): void
    {
        $adapter = self::adapter();
        self::deleteFixtures();

        $adapter->delete(\Maho\Queue\QueueManager::tableName(), ['message_id > ?' => self::$lastQueueMessageId]);

        if (self::$snapshot === null) {
            return;
        }
        foreach (self::AGGREGATED_TABLES as $table) {
            $adapter->delete(self::table($table));
            // PostgreSQL accepts 65535 values in one insert, and the sample data fills more than that.
            foreach (array_chunk(self::$snapshot[$table], 1000) as $rows) {
                $adapter->insertMultiple(self::table($table), $rows);
            }
        }
        $adapter->delete(self::table('core/flag'), ['flag_code IN (?)' => self::flagCodes()]);
        if (self::$snapshot['core_flag'] !== []) {
            $adapter->insertMultiple(self::table('core/flag'), self::$snapshot['core_flag']);
        }
        foreach (self::$snapshot['eav_entity_store'] as $row) {
            $adapter->update(
                self::table('eav/entity_store'),
                ['increment_last_id' => $row['increment_last_id']],
                ['entity_store_id = ?' => $row['entity_store_id']],
            );
        }
        self::$snapshot = null;
    }

    /**
     * @return list<string>
     */
    public static function flagCodes(): array
    {
        return array_values(array_column(\Mage_Reports_Model_Statistics::REPORTS, 'flag'));
    }

    /**
     * An enabled simple product in stock on website 1.
     *
     * @param array<string, mixed> $stockData replaces keys of the default stock data
     */
    public static function createProduct(string $prefix, float $price = 10.0, int $taxClassId = 0, array $stockData = []): \Mage_Catalog_Model_Product
    {
        ApiV2Helper::ensureMahoBootstrapped();
        /** @var \Mage_Catalog_Model_Product $product */
        $product = \Mage::getModel('catalog/product');
        $product->setStoreId(\Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setSku($prefix . '-' . uniqid())
            ->setName('Report Fixture ' . $prefix)
            ->setPrice($price)
            ->setWeight(1)
            ->setStatus(\Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTypeId(\Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setTaxClassId($taxClassId)
            ->setWebsiteIds([1])
            ->setStockData($stockData + ['use_config_manage_stock' => 0, 'manage_stock' => 1, 'qty' => 1000, 'is_in_stock' => 1])
            ->save();
        self::$productIds[] = (int) $product->getId();
        return \Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
    }

    /**
     * A customer of website 1.
     */
    public static function createCustomer(string $prefix, ?string $createdAt = null): \Mage_Customer_Model_Customer
    {
        ApiV2Helper::ensureMahoBootstrapped();
        /** @var \Mage_Customer_Model_Customer $customer */
        $customer = \Mage::getModel('customer/customer');
        $customer->setWebsiteId(1)
            ->setStoreId(1)
            ->setGroupId(1)
            ->setFirstname('Report')
            ->setLastname(ucfirst($prefix))
            ->setEmail($prefix . '.' . uniqid() . '@example.test')
            ->save();
        self::$customerIds[] = (int) $customer->getId();
        if ($createdAt !== null) {
            self::adapter()->update(self::table('customer/entity'), ['created_at' => $createdAt, 'updated_at' => $createdAt], ['entity_id = ?' => $customer->getId()]);
        }
        return $customer;
    }

    /**
     * Place an order of $qty items of $product in store 1, invoice it when $invoice is true, and move
     * its dates to $createdAt (UTC). An order that is not invoiced gets the state processing, because
     * the aggregation leaves out the orders in the state new.
     */
    public static function placeOrder(
        \Mage_Catalog_Model_Product $product,
        int $qty = 2,
        ?string $createdAt = null,
        bool $invoice = true,
        ?\Mage_Customer_Model_Customer $customer = null,
        ?string $couponCode = null,
    ): \Mage_Sales_Model_Order {
        ApiV2Helper::ensureMahoBootstrapped();
        $quote = \createPlaceableQuote($product, $qty);
        if ($couponCode !== null) {
            $quote->setCouponCode($couponCode)->setTotalsCollectedFlag(false)->collectTotals()->save();
        }
        if ($customer !== null) {
            $quote->assignCustomer($customer);
            $quote->setCustomerId((int) $customer->getId())->setCustomerIsGuest(false)->setCustomerEmail($customer->getEmail());
            $quote->collectTotals()->save();
        }
        self::$quoteIds[] = (int) $quote->getId();

        $service = new \Mage_Sales_Model_Service_Quote($quote);
        $service->submitAll();
        $order = $service->getOrder();
        self::$orderIds[] = (int) $order->getId();

        if ($invoice) {
            $invoiceModel = $order->prepareInvoice();
            $invoiceModel->setRequestedCaptureCase(\Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoiceModel->register();
            $order->setIsInProcess(true);
            \Mage::getModel('core/resource_transaction')->addObject($invoiceModel)->addObject($order)->save();
        } else {
            $order->setState(\Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
        }

        if ($createdAt !== null) {
            self::moveOrderDates((int) $order->getId(), $createdAt);
        }
        return \Mage::getModel('sales/order')->load($order->getId());
    }

    /**
     * Ship all items of an invoiced order.
     */
    public static function shipOrder(\Mage_Sales_Model_Order $order): \Mage_Sales_Model_Order
    {
        $shipment = $order->prepareShipment();
        $shipment->register();
        $order->setIsInProcess(true);
        \Mage::getModel('core/resource_transaction')->addObject($shipment)->addObject($order)->save();
        return \Mage::getModel('sales/order')->load($order->getId());
    }

    /**
     * Refund all items of an invoiced order offline.
     */
    public static function refundOrder(\Mage_Sales_Model_Order $order): \Mage_Sales_Model_Order
    {
        /** @var \Mage_Sales_Model_Service_Order $service */
        $service = \Mage::getModel('sales/service_order', $order);
        $creditmemo = $service->prepareCreditmemo();
        $creditmemo->setOfflineRequested(true);
        $creditmemo->register();
        \Mage::getModel('core/resource_transaction')->addObject($creditmemo)->addObject($creditmemo->getOrder())->save();
        return \Mage::getModel('sales/order')->load($order->getId());
    }

    /**
     * An active cart price rule with the coupon code $code and a discount of $percent percent, for website 1.
     */
    public static function createCouponRule(string $code, float $percent = 10.0): \Mage_SalesRule_Model_Rule
    {
        ApiV2Helper::ensureMahoBootstrapped();
        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->setName('Report Fixture ' . $code)
            ->setIsActive(true)
            ->setWebsiteIds([1])
            ->setCustomerGroupIds([0, 1, 2, 3])
            ->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($code)
            ->setSimpleAction(\Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount($percent)
            ->save();
        self::$ruleIds[] = (int) $rule->getId();
        return $rule;
    }

    /**
     * Add $count product view events of $productId in store $storeId at $loggedAt (UTC).
     */
    public static function addProductViews(int $productId, int $count, string $loggedAt, int $storeId = 1): void
    {
        $adapter = self::adapter();
        for ($i = 0; $i < $count; $i++) {
            $adapter->insert(self::table('reports/event'), [
                'logged_at' => $loggedAt,
                'event_type_id' => \Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW,
                'object_id' => $productId,
                'subject_id' => 0,
                'subtype' => 0,
                'store_id' => $storeId,
            ]);
            self::$eventIds[] = (int) $adapter->lastInsertId(self::table('reports/event'));
        }
    }

    /**
     * A search term of store $storeId.
     */
    public static function addSearchTerm(string $text, int $results, int $uses, string $updatedAt, int $storeId = 1): int
    {
        $adapter = self::adapter();
        $adapter->insert(self::table('catalogsearch/search_query'), [
            'query_text' => $text,
            'num_results' => $results,
            'popularity' => $uses,
            'store_id' => $storeId,
            'display_in_terms' => 1,
            'is_active' => 1,
            'is_processed' => 0,
            'updated_at' => $updatedAt,
        ]);
        $id = (int) $adapter->lastInsertId(self::table('catalogsearch/search_query'));
        self::$searchQueryIds[] = $id;
        return $id;
    }

    /**
     * An active cart of store 1 with $qty items of $product, for a guest or for $customer.
     */
    public static function createCart(\Mage_Catalog_Model_Product $product, int $qty = 1, ?\Mage_Customer_Model_Customer $customer = null): \Mage_Sales_Model_Quote
    {
        ApiV2Helper::ensureMahoBootstrapped();
        $quote = \createPlaceableQuote($product, $qty);
        if ($customer !== null) {
            $quote->assignCustomer($customer);
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        }
        self::$quoteIds[] = (int) $quote->getId();
        return $quote;
    }

    /**
     * A visit of the visitor log in store $storeId. $urls are the pages of the visit, in order.
     *
     * @param list<string> $urls
     */
    public static function addVisit(
        int $storeId,
        string $firstVisitAt,
        string $userAgent,
        string $language,
        string $referer,
        string $remoteAddr,
        array $urls,
    ): int {
        $adapter = self::adapter();
        $urlIds = [];
        foreach ($urls as $url) {
            $adapter->insert(self::table('log/url_info_table'), ['url' => $url, 'referer' => $referer]);
            $urlIds[] = (int) $adapter->lastInsertId(self::table('log/url_info_table'));
        }
        self::$urlIds = array_merge(self::$urlIds, $urlIds);

        $adapter->insert(self::table('log/visitor'), [
            'session_id' => 'reportfixture' . uniqid(),
            'first_visit_at' => $firstVisitAt,
            'last_visit_at' => $firstVisitAt,
            'last_url_id' => $urlIds === [] ? 0 : $urlIds[count($urlIds) - 1],
            'store_id' => $storeId,
        ]);
        $visitorId = (int) $adapter->lastInsertId(self::table('log/visitor'));
        self::$visitorIds[] = $visitorId;

        $adapter->insert(self::table('log/visitor_info'), [
            'visitor_id' => $visitorId,
            'http_referer' => $referer,
            'http_user_agent' => $userAgent,
            'http_accept_language' => $language,
            'remote_addr' => inet_pton($remoteAddr),
        ]);
        foreach ($urlIds as $urlId) {
            $adapter->insert(self::table('log/url_table'), ['url_id' => $urlId, 'visitor_id' => $visitorId, 'visit_time' => $firstVisitAt]);
        }
        \Mage::app()->getCache()->clean([\Mage_Log_Helper_Dashboard::CACHE_TAG]);
        return $visitorId;
    }

    /**
     * Set the created and updated dates of an order and of its documents to $date (UTC).
     */
    public static function moveOrderDates(int $orderId, string $date): void
    {
        $adapter = self::adapter();
        $dates = ['created_at' => $date, 'updated_at' => $date];
        $adapter->update(self::table('sales/order'), $dates, ['entity_id = ?' => $orderId]);
        $adapter->update(self::table('sales/order_grid'), $dates, ['entity_id = ?' => $orderId]);
        $adapter->update(self::table('sales/order_item'), $dates, ['order_id = ?' => $orderId]);
        foreach (['invoice', 'shipment', 'creditmemo'] as $document) {
            $adapter->update(self::table("sales/{$document}"), $dates, ['order_id = ?' => $orderId]);
            $adapter->update(self::table("sales/{$document}_grid"), ['created_at' => $date], ['order_id = ?' => $orderId]);
        }
    }

    /**
     * Aggregate the statistics of $codes for the local dates from $from to $to, in the admin store as the cron does.
     *
     * @param list<string> $codes
     */
    public static function aggregate(array $codes, string $from, string $to): void
    {
        ApiV2Helper::ensureMahoBootstrapped();
        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        $statistics->withAdminStore(function () use ($statistics, $codes, $from, $to): void {
            foreach ($codes as $code) {
                \Mage::getResourceModel($statistics->getResourceModelName($code))->aggregate("{$from} 00:00:00", "{$to} 23:59:59");
            }
        });
    }

    /**
     * Delete the orders $ids with their items, addresses, payments, history and documents.
     *
     * @param list<int> $ids
     */
    public static function deleteOrders(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $adapter = self::adapter();
        foreach (['invoice', 'shipment', 'creditmemo'] as $document) {
            $documentIds = $adapter->fetchCol(
                $adapter->select()->from(self::table("sales/{$document}"), ['entity_id'])->where('order_id IN (?)', $ids),
            );
            if ($documentIds !== []) {
                if ($document === 'shipment') {
                    $adapter->delete(self::table('sales/shipment_track'), ['parent_id IN (?)' => $documentIds]);
                }
                $adapter->delete(self::table("sales/{$document}_item"), ['parent_id IN (?)' => $documentIds]);
                $adapter->delete(self::table("sales/{$document}_comment"), ['parent_id IN (?)' => $documentIds]);
                $adapter->delete(self::table("sales/{$document}_grid"), ['entity_id IN (?)' => $documentIds]);
                $adapter->delete(self::table("sales/{$document}"), ['entity_id IN (?)' => $documentIds]);
            }
        }
        $adapter->delete(self::table('sales/order_item'), ['order_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order_address'), ['parent_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order_payment'), ['parent_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order_status_history'), ['parent_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order_tax'), ['order_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order_grid'), ['entity_id IN (?)' => $ids]);
        $adapter->delete(self::table('sales/order'), ['entity_id IN (?)' => $ids]);
    }

    /**
     * Delete the orders, quotes, customers and products of the fixtures.
     */
    public static function deleteFixtures(): void
    {
        $adapter = self::adapter();
        if (self::$orderIds !== []) {
            self::deleteOrders(self::$orderIds);
            self::$orderIds = [];
        }
        if (self::$quoteIds !== []) {
            $adapter->delete(self::table('sales/quote_item'), ['quote_id IN (?)' => self::$quoteIds]);
            $adapter->delete(self::table('sales/quote_payment'), ['quote_id IN (?)' => self::$quoteIds]);
            $adapter->delete(self::table('sales/quote_address'), ['quote_id IN (?)' => self::$quoteIds]);
            $adapter->delete(self::table('sales/quote'), ['entity_id IN (?)' => self::$quoteIds]);
            self::$quoteIds = [];
        }
        if (self::$visitorIds !== []) {
            $adapter->delete(self::table('log/url_table'), ['visitor_id IN (?)' => self::$visitorIds]);
            $adapter->delete(self::table('log/visitor_info'), ['visitor_id IN (?)' => self::$visitorIds]);
            $adapter->delete(self::table('log/visitor'), ['visitor_id IN (?)' => self::$visitorIds]);
            self::$visitorIds = [];
            \Mage::app()->getCache()->clean([\Mage_Log_Helper_Dashboard::CACHE_TAG]);
        }
        if (self::$urlIds !== []) {
            $adapter->delete(self::table('log/url_info_table'), ['url_id IN (?)' => self::$urlIds]);
            self::$urlIds = [];
        }
        if (self::$searchQueryIds !== []) {
            $adapter->delete(self::table('catalogsearch/search_query'), ['query_id IN (?)' => self::$searchQueryIds]);
            self::$searchQueryIds = [];
        }
        if (self::$eventIds !== []) {
            $adapter->delete(self::table('reports/event'), ['event_id IN (?)' => self::$eventIds]);
            self::$eventIds = [];
        }
        if (self::$ruleIds !== []) {
            $adapter->delete(self::table('salesrule/coupon'), ['rule_id IN (?)' => self::$ruleIds]);
            $adapter->delete(self::table('salesrule/rule'), ['rule_id IN (?)' => self::$ruleIds]);
            self::$ruleIds = [];
        }
        if (self::$customerIds !== []) {
            $adapter->delete(self::table('customer/entity'), ['entity_id IN (?)' => self::$customerIds]);
            self::$customerIds = [];
        }
        if (self::$productIds !== []) {
            $emulation = \Mage::getSingleton('core/app_emulation');
            $environment = $emulation->startEnvironmentEmulation(0, 'admin');
            try {
                foreach (self::$productIds as $id) {
                    $product = \Mage::getModel('catalog/product')->load($id);
                    if ($product->getId()) {
                        $product->delete();
                    }
                }
            } finally {
                $emulation->stopEnvironmentEmulation($environment);
            }
            self::$productIds = [];
        }
    }
}
