<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 global search of the admin.
 *
 * @group read
 */

const GLOBAL_SEARCH_PATH = '/api/rest/v2/global-search';
const GLOBAL_SEARCH_ENABLE = 'admin/global_search/enable';

beforeAll(function (): void {
    ReportFixture::snapshot();
    $suffix = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
    $product = ReportFixture::createProduct('Gsearch' . $suffix);
    $customer = ReportFixture::createCustomer('gsearch' . $suffix);
    $order = ReportFixture::placeOrder($product, invoice: false);
    $foreignOrder = ReportFixture::placeOrder($product, invoice: false);
    $foreignStoreId = globalSearchForeignStoreId();
    if ($foreignStoreId !== null) {
        ReportFixture::adapter()->update(ReportFixture::table('sales/order'), ['store_id' => $foreignStoreId], ['entity_id = ?' => $foreignOrder->getId()]);
    }
    globalSearchFixture([
        'product' => $product,
        'customer' => $customer,
        'order' => $order,
        'foreignOrder' => $foreignOrder,
        'foreignStoreId' => $foreignStoreId,
    ]);
});

afterAll(function (): void {
    globalSearchSetEnabled(null);
    ReportFixture::restore();
    cleanupTestData();
});

/**
 * @param array<string, mixed>|null $set
 * @return array<string, mixed>
 */
function globalSearchFixture(?array $set = null): array
{
    static $fixture = [];
    if ($set !== null) {
        $fixture = $set;
    }
    return $fixture;
}

/**
 * A store of a website that does not have store 1, or null when there is none.
 */
function globalSearchForeignStoreId(): ?int
{
    $ownWebsite = (int) Mage::app()->getStore(1)->getWebsiteId();
    foreach (Mage::app()->getStores() as $store) {
        if ((int) $store->getWebsiteId() !== $ownWebsite) {
            return (int) $store->getId();
        }
    }
    return null;
}

/**
 * Set the enable flag at the default scope. Null puts back the value from before the test.
 */
function globalSearchSetEnabled(?bool $enabled): void
{
    static $original = false;
    $adapter = ReportFixture::adapter();
    $table = ReportFixture::table('core/config_data');
    $where = ['path = ?' => GLOBAL_SEARCH_ENABLE, 'scope = ?' => 'default', 'scope_id = ?' => 0];
    if ($original === false) {
        $value = $adapter->fetchOne($adapter->select()->from($table, ['value'])->where('path = ?', GLOBAL_SEARCH_ENABLE)
            ->where('scope = ?', 'default')->where('scope_id = ?', 0));
        $original = $value === false ? null : (string) $value;
    }
    if ($enabled === null) {
        if ($original === null) {
            $adapter->delete($table, $where);
        } else {
            Mage::getModel('core/config')->saveConfig(GLOBAL_SEARCH_ENABLE, $original, 'default', 0);
        }
    } else {
        Mage::getModel('core/config')->saveConfig(GLOBAL_SEARCH_ENABLE, $enabled ? '1' : '0', 'default', 0);
    }
    Mage::app()->getCache()->cleanType('config');
}

/**
 * @return list<array<string, mixed>>
 */
function globalSearchItems(string $query, string $token, int $limit = 25, ?string $storeCode = null): array
{
    $headers = $storeCode === null ? [] : ['X-Store-Code' => $storeCode];
    $response = apiGet(GLOBAL_SEARCH_PATH . '?limit=' . $limit . '&query=' . rawurlencode($query), $token, $headers);
    expect($response['status'])->toBe(200, (string) json_encode($response['json']));
    return $response['json']['items'];
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<int>
 */
function globalSearchIds(array $items, string $entity): array
{
    return array_values(array_map(
        static fn(array $item): int => $item['entityId'],
        array_filter($items, static fn(array $item): bool => $item['entity'] === $entity),
    ));
}

/**
 * The increment ID of $order without its last character, which also matches other orders.
 */
function globalSearchOrderPrefix(Mage_Sales_Model_Order $order): string
{
    return substr((string) $order->getIncrementId(), 0, -1);
}

describe('Global search', function (): void {

    it('finds an order by the prefix of its increment ID', function (): void {
        $order = globalSearchFixture()['order'];
        $items = globalSearchItems(globalSearchOrderPrefix($order), adminToken());

        $item = array_values(array_filter($items, static fn(array $item): bool => $item['entityId'] === (int) $order->getId() && $item['entity'] === 'order'));
        expect($item)->toHaveCount(1)
            ->and($item[0])->toBe([
                'source' => 'sales',
                'type' => 'Order',
                'entity' => 'order',
                'entityId' => (int) $order->getId(),
                'name' => 'Order #' . $order->getIncrementId(),
                'description' => $item[0]['description'],
            ])
            ->and(array_keys($item[0]))->not->toContain('url')
            ->and(array_keys($item[0]))->not->toContain('id');
    });

    it('finds a customer by name', function (): void {
        $customer = globalSearchFixture()['customer'];
        // The SKU of the fixture product starts with the same text, so the product is also a result
        $items = array_values(array_filter(
            globalSearchItems((string) $customer->getLastname(), adminToken()),
            static fn(array $item): bool => $item['source'] === 'customers',
        ));

        expect($items)->toBe([[
            'source' => 'customers',
            'type' => 'Customer',
            'entity' => 'customer',
            'entityId' => (int) $customer->getId(),
            'name' => $customer->getName(),
            'description' => null,
        ]]);
    });

    it('finds a product by name', function (): void {
        $product = globalSearchFixture()['product'];
        $items = globalSearchItems((string) $product->getName(), adminToken());

        expect(globalSearchIds($items, 'product'))->toBe([(int) $product->getId()])
            ->and($items[0]['source'])->toBe('products')
            ->and($items[0]['type'])->toBe('Product')
            ->and($items[0]['name'])->toBe($product->getName());
    });

    it('limits the results of each source', function (): void {
        $order = globalSearchFixture()['order'];
        $items = globalSearchItems(substr((string) $order->getIncrementId(), 0, 3), adminToken(), 1);

        expect(globalSearchIds($items, 'order'))->toHaveCount(1)
            ->and(count($items))->toBeLessThanOrEqual(3);
    });

    it('returns no items for a query that is too short', function (): void {
        foreach (['', '1', ' 1 '] as $query) {
            $response = apiGet(GLOBAL_SEARCH_PATH . '?query=' . rawurlencode($query), adminToken());
            expect($response['status'])->toBe(200)
                ->and($response['json'])->toBe(['query' => trim($query), 'items' => []]);
        }
        expect(apiGet(GLOBAL_SEARCH_PATH, adminToken())['json'])->toBe(['query' => '', 'items' => []]);
    });

    it('rejects a limit that is not an integer', function (): void {
        expect(apiGet(GLOBAL_SEARCH_PATH . '?query=100&limit=many', adminToken())['status'])->toBe(400);
    });

    it('applies the admin ACL of each source', function (): void {
        $fixture = globalSearchFixture();
        $token = adminTokenWithAcl(['admin/global_search', 'admin/customer', 'admin/catalog'], 'apitest_gsearch_nosales');

        expect(globalSearchIds(globalSearchItems(globalSearchOrderPrefix($fixture['order']), $token), 'order'))->toBe([])
            ->and(globalSearchIds(globalSearchItems((string) $fixture['customer']->getLastname(), $token), 'customer'))
            ->toBe([(int) $fixture['customer']->getId()]);

        $noSearch = adminTokenWithAcl(['admin/sales', 'admin/customer', 'admin/catalog'], 'apitest_gsearch_denied');
        expect(apiGet(GLOBAL_SEARCH_PATH . '?query=100', $noSearch)['status'])->toBe(403);
    });

    it('gives an API user the sources of its permissions', function (): void {
        $fixture = globalSearchFixture();
        $orderQuery = globalSearchOrderPrefix($fixture['order']);
        $customerQuery = (string) $fixture['customer']->getLastname();

        $orders = serviceToken(['global-search/read', 'orders/read']);
        expect(globalSearchIds(globalSearchItems($orderQuery, $orders), 'order'))->toContain((int) $fixture['order']->getId())
            ->and(globalSearchItems($customerQuery, $orders))->toBe([]);

        $searchOnly = serviceToken(['global-search/read']);
        expect(globalSearchItems($orderQuery, $searchOnly))->toBe([]);

        expect(apiGet(GLOBAL_SEARCH_PATH . '?query=100', serviceToken(['orders/read']))['status'])->toBe(403);
    });

    it('keeps a token with a store restriction in its stores', function (): void {
        $fixture = globalSearchFixture();
        if ($fixture['foreignStoreId'] === null) {
            $this->markTestSkipped('The store has no second website');
        }
        $orderId = (int) $fixture['order']->getId();
        $foreignOrderId = (int) $fixture['foreignOrder']->getId();

        $restricted = serviceToken(['all'], [1]);
        $ids = globalSearchIds(globalSearchItems((string) $fixture['foreignOrder']->getIncrementId(), $restricted), 'order');
        expect($ids)->not->toContain($foreignOrderId);
        $ids = globalSearchIds(globalSearchItems((string) $fixture['order']->getIncrementId(), $restricted), 'order');
        expect($ids)->toBe([$orderId]);

        $foreign = serviceToken(['all'], [$fixture['foreignStoreId']]);
        $storeCode = (string) Mage::app()->getStore($fixture['foreignStoreId'])->getCode();
        $customerQuery = (string) $fixture['customer']->getLastname();
        expect(globalSearchIds(globalSearchItems((string) $fixture['foreignOrder']->getIncrementId(), $foreign, storeCode: $storeCode), 'order'))
            ->toBe([$foreignOrderId])
            ->and(globalSearchIds(globalSearchItems($customerQuery, $foreign, storeCode: $storeCode), 'customer'))->toBe([])
            ->and(globalSearchIds(globalSearchItems($customerQuery, $foreign, storeCode: $storeCode), 'product'))->toBe([])
            ->and(globalSearchIds(globalSearchItems($customerQuery, $restricted), 'customer'))
            ->toBe([(int) $fixture['customer']->getId()]);
    });

    it('needs the enable flag', function (): void {
        globalSearchSetEnabled(false);
        try {
            $response = apiGet(GLOBAL_SEARCH_PATH . '?query=100', adminToken());
            expect($response['status'])->toBe(403)
                ->and((string) json_encode($response['json']))->toContain('global search is disabled');
        } finally {
            globalSearchSetEnabled(null);
        }
        expect(apiGet(GLOBAL_SEARCH_PATH . '?query=100', adminToken())['status'])->toBe(200);
    });

    it('denies guests and customers', function (): void {
        expect(apiGet(GLOBAL_SEARCH_PATH . '?query=100')['status'])->toBe(401)
            ->and(apiGet(GLOBAL_SEARCH_PATH . '?query=100', customerToken())['status'])->toBe(403);
    });
});
