<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 lists of the invoices, shipments and credit memos of all orders.
 *
 * @group write
 */

use Tests\Helpers\ApiV2Helper;

/**
 * An order of qty 2 with two invoices, two shipments and two credit memos of qty 1.
 * The billing and shipping name is "Gridfirst Gridlast{token}".
 *
 * @return array{token: string, orderId: int, orderIncrementId: string, invoices: list<int>, shipments: list<int>, creditMemos: list<int>}|null
 */
function salesListOrder(): ?array
{
    static $order = false;
    if ($order !== false) {
        return $order;
    }
    $order = null;

    ApiV2Helper::ensureMahoBootstrapped();
    $token = strtolower(substr(bin2hex(random_bytes(6)), 0, 10));

    $sku = null;
    $products = Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->addAttributeToFilter('visibility', ['neq' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE])
        ->addAttributeToFilter('required_options', 0)
        ->setPageSize(20);
    foreach ($products as $product) {
        if ($product->isSalable()) {
            $sku = (string) $product->getSku();
            break;
        }
    }
    if ($sku === null) {
        return null;
    }

    $address = [
        'firstName' => 'Gridfirst',
        'lastName' => 'Gridlast' . $token,
        'street' => ['1 Grid Way'],
        'city' => 'Los Angeles',
        'region' => 'California',
        'postcode' => '90210',
        'countryId' => 'US',
        'telephone' => '5550100',
    ];

    $cart = apiPost('/api/rest/v2/carts', [], customerToken());
    $cartId = (int) ($cart['json']['id'] ?? 0);
    if (!$cartId) {
        return null;
    }
    trackCreated('quote', $cartId);
    apiPost("/api/rest/v2/carts/{$cartId}/items", ['sku' => $sku, 'qty' => 2], customerToken());

    $place = apiPost('/api/rest/v2/orders', [
        'cartId' => $cartId,
        'shippingAddress' => $address,
        'billingAddress' => $address,
        'paymentMethod' => 'cashondelivery',
        'shippingMethod' => 'freeshipping_freeshipping',
    ], customerToken());
    $orderId = (int) ($place['json']['id'] ?? 0);
    if (!$orderId) {
        return null;
    }
    trackCreated('order', $orderId);

    $model = Mage::getModel('sales/order')->load($orderId);
    $itemId = 0;
    foreach ($model->getAllVisibleItems() as $item) {
        $itemId = (int) $item->getId();
    }
    $items = [['orderItemId' => $itemId, 'qty' => 1]];

    $ids = ['invoices' => [], 'shipments' => [], 'creditMemos' => []];
    foreach ([['invoices', '/invoices', ['capture' => 'offline']], ['shipments', '/shipments', []], ['creditMemos', '/credit-memos', ['offlineRefund' => true]]] as [$key, $path, $body]) {
        for ($i = 0; $i < 2; $i++) {
            $response = apiPost("/api/rest/v2/orders/{$orderId}{$path}", ['items' => $items] + $body, adminToken());
            if (empty($response['json']['id'])) {
                return null;
            }
            $ids[$key][] = (int) $response['json']['id'];
        }
    }

    $order = ['token' => $token, 'orderId' => $orderId, 'orderIncrementId' => (string) $model->getIncrementId()] + $ids;
    return $order;
}

function salesListOrderOrSkip(): array
{
    $order = salesListOrder();
    if ($order === null) {
        test()->markTestSkipped('Could not place, invoice, ship and refund an order in this store');
    }
    return $order;
}

function salesListIds(array $response): array
{
    return array_map(intval(...), array_column($response['json']['member'] ?? [], 'id'));
}

// The API users of serviceToken() stay: the helper caches them for the whole run, and
// later test files reuse the same tokens.
afterAll(function (): void {
    cleanupTestData();
});

describe('GET /api/rest/v2/invoices, /shipments and /credit-memos', function (): void {

    it('lists the documents of all orders with the fields of the admin grid', function (string $path, string $key, string $nameField): void {
        $order = salesListOrderOrSkip();

        $response = apiGet("{$path}?search=Gridlast{$order['token']}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['totalItems'])->toBe(2);
        expect(salesListIds($response))->toBe(array_reverse($order[$key]));
        foreach ($response['json']['member'] as $document) {
            expect($document['orderId'])->toBe($order['orderId']);
            expect($document['orderIncrementId'])->toBe($order['orderIncrementId']);
            expect($document[$nameField])->toBe("Gridfirst Gridlast{$order['token']}");
            expect($document['incrementId'])->not->toBeEmpty();
            expect($document['createdAt'])->not->toBeEmpty();
            if (array_key_exists('totalQty', $document)) {
                expect((float) $document['totalQty'])->toBe(1.0);
            }
        }
    })->with([
        'invoices' => ['/api/rest/v2/invoices', 'invoices', 'billingName'],
        'shipments' => ['/api/rest/v2/shipments', 'shipments', 'shippingName'],
        'credit memos' => ['/api/rest/v2/credit-memos', 'creditMemos', 'billingName'],
    ]);

    it('pages the list, newest first, and counts every match', function (string $path, string $key): void {
        $order = salesListOrderOrSkip();

        $first = apiGet("{$path}?itemsPerPage=1&search=Gridlast{$order['token']}", adminToken());
        $second = apiGet("{$path}?itemsPerPage=1&page=2&search=Gridlast{$order['token']}", adminToken());

        expect($first['json']['totalItems'])->toBe(2);
        expect($second['json']['totalItems'])->toBe(2);
        expect(salesListIds($first))->toBe([$order[$key][1]]);
        expect(salesListIds($second))->toBe([$order[$key][0]]);

        $all = apiGet("{$path}?itemsPerPage=5", adminToken());
        $dates = array_column($all['json']['member'], 'createdAt');
        $sorted = $dates;
        rsort($sorted);
        expect($dates)->toBe($sorted);
    })->with([
        'invoices' => ['/api/rest/v2/invoices', 'invoices'],
        'shipments' => ['/api/rest/v2/shipments', 'shipments'],
        'credit memos' => ['/api/rest/v2/credit-memos', 'creditMemos'],
    ]);

    it('searches the document number, the order number and the name, ignoring case', function (string $path, string $key): void {
        $order = salesListOrderOrSkip();
        $document = apiGet("{$path}/{$order[$key][0]}", adminToken());
        expect($document['status'])->toBe(200);

        $byNumber = apiGet("{$path}?search=" . urlencode($document['json']['incrementId']), adminToken());
        expect(salesListIds($byNumber))->toContain($order[$key][0]);

        $byOrder = apiGet("{$path}?search=" . urlencode($order['orderIncrementId'] . ' GRIDFIRST ' . strtoupper("gridlast{$order['token']}")), adminToken());
        expect(salesListIds($byOrder))->toEqualCanonicalizing($order[$key]);

        $noMatch = apiGet("{$path}?search=" . urlencode("Gridlast{$order['token']} zzqx-no-match"), adminToken());
        expect($noMatch['json']['member'])->toBe([]);
        expect($noMatch['json']['totalItems'])->toBe(0);
    })->with([
        'invoices' => ['/api/rest/v2/invoices', 'invoices'],
        'shipments' => ['/api/rest/v2/shipments', 'shipments'],
        'credit memos' => ['/api/rest/v2/credit-memos', 'creditMemos'],
    ]);

    it('uses only the first words of a long search', function (): void {
        $order = salesListOrderOrSkip();
        $words = array_fill(0, \Mage\Sales\Api\InvoiceProvider::MAX_SEARCH_WORDS, "Gridlast{$order['token']}");
        $words[] = 'zzqx-no-match';

        $response = apiGet('/api/rest/v2/invoices?search=' . urlencode(implode(' ', $words)), adminToken());

        expect(salesListIds($response))->toEqualCanonicalizing($order['invoices']);
    });

    it('filters by order, state and creation date', function (): void {
        $order = salesListOrderOrSkip();
        $search = "search=Gridlast{$order['token']}";

        expect(apiGet("/api/rest/v2/shipments?orderId={$order['orderId']}", adminToken())['json']['totalItems'])->toBe(2);
        expect(apiGet("/api/rest/v2/invoices?{$search}&orderId=999999999", adminToken())['json']['totalItems'])->toBe(0);

        expect(apiGet("/api/rest/v2/invoices?{$search}&state=paid", adminToken())['json']['totalItems'])->toBe(2);
        expect(apiGet("/api/rest/v2/invoices?{$search}&state=open", adminToken())['json']['totalItems'])->toBe(0);
        expect(apiGet("/api/rest/v2/credit-memos?{$search}&state=refunded", adminToken())['json']['totalItems'])->toBe(2);
        expect(apiGet("/api/rest/v2/credit-memos?{$search}&state=canceled", adminToken())['json']['totalItems'])->toBe(0);

        $today = gmdate('Y-m-d');
        $tomorrow = gmdate('Y-m-d', time() + 86400);
        expect(apiGet("/api/rest/v2/credit-memos?{$search}&createdFrom={$today}&createdTo={$today}", adminToken())['json']['totalItems'])->toBe(2);
        expect(apiGet("/api/rest/v2/shipments?{$search}&createdFrom={$tomorrow}", adminToken())['json']['totalItems'])->toBe(0);
    });

    it('rejects a wrong filter value', function (string $query, string $name): void {
        $response = apiGet("/api/rest/v2/{$query}", adminToken());

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? $response['json']['detail'] ?? '')->toContain($name);
    })->with([
        'unknown state' => ['invoices?state=refunded', 'state'],
        'state list' => ['credit-memos?state[]=open', 'state'],
        'search list' => ['shipments?search[]=x', 'search'],
        'orderId text' => ['invoices?orderId=abc', 'orderId'],
    ]);

    it('denies guests, customers and service tokens without the grant', function (string $path, string $permission): void {
        expect(apiGet($path)['status'])->toBe(401);
        expect(apiGet($path, customerToken())['status'])->toBe(403);
        expect(apiGet($path, serviceToken(['orders/read']))['status'])->toBe(403);
        expect(apiGet($path, serviceToken([$permission]))['status'])->toBe(200);
    })->with([
        'invoices' => ['/api/rest/v2/invoices', 'invoices/read'],
        'shipments' => ['/api/rest/v2/shipments', 'shipments/read'],
        'credit memos' => ['/api/rest/v2/credit-memos', 'credit-memos/read'],
    ]);

    it('keeps a store-restricted token in its stores', function (string $path, string $key, string $permission, string $table): void {
        $order = salesListOrderOrSkip();
        $outside = $order[$key][0];
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $restricted = serviceToken([$permission], [1]);

        // Store 0 exists in every install and no restricted token may read it
        $write->update($table, ['store_id' => 0], ['entity_id = ?' => $outside]);
        try {
            $response = apiGet("{$path}?search=Gridlast{$order['token']}", $restricted);
            expect($response['status'])->toBe(200);
            expect(salesListIds($response))->toBe([$order[$key][1]]);
            expect($response['json']['totalItems'])->toBe(1);
            expect(apiGet("{$path}/{$outside}", $restricted)['status'])->toBe(403);

            expect(salesListIds(apiGet("{$path}?search=Gridlast{$order['token']}", adminToken())))->toContain($outside);
        } finally {
            $write->update($table, ['store_id' => 1], ['entity_id = ?' => $outside]);
        }
    })->with([
        'invoices' => ['/api/rest/v2/invoices', 'invoices', 'invoices/read', 'sales_flat_invoice'],
        'shipments' => ['/api/rest/v2/shipments', 'shipments', 'shipments/read', 'sales_flat_shipment'],
        'credit memos' => ['/api/rest/v2/credit-memos', 'creditMemos', 'credit-memos/read', 'sales_flat_creditmemo'],
    ]);
});

describe('GET /api/rest/v2/invoices/{id}', function (): void {

    it('returns one invoice with its items, name and PDF link', function (): void {
        $order = salesListOrderOrSkip();
        $id = $order['invoices'][0];

        $response = apiGet("/api/rest/v2/invoices/{$id}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['id'])->toBe($id);
        expect($response['json']['orderIncrementId'])->toBe($order['orderIncrementId']);
        expect($response['json']['billingName'])->toBe("Gridfirst Gridlast{$order['token']}");
        expect($response['json']['stateName'])->toBe('paid');
        expect($response['json']['items'])->toHaveCount(1);
        expect($response['json']['pdfUrl'])->toBe("/api/rest/v2/orders/{$order['orderId']}/invoices/{$id}/pdf");
    });

    it('denies customers and service tokens without the grant, and returns 404 for an unknown id', function (): void {
        $order = salesListOrderOrSkip();
        $id = $order['invoices'][0];

        expect(apiGet("/api/rest/v2/invoices/{$id}", customerToken())['status'])->toBe(403);
        expect(apiGet("/api/rest/v2/invoices/{$id}", serviceToken(['orders/read']))['status'])->toBe(403);
        expect(apiGet("/api/rest/v2/invoices/{$id}", serviceToken(['invoices/read']))['status'])->toBe(200);
        expect(apiGet('/api/rest/v2/invoices/999999999', adminToken())['status'])->toBe(404);
    });

    it('keeps the name and order number in the lists of one order', function (): void {
        $order = salesListOrderOrSkip();

        $invoices = apiGet("/api/rest/v2/orders/{$order['orderId']}/invoices", adminToken());
        expect($invoices['status'])->toBe(200);
        expect(array_unique(array_column(getItems($invoices), 'billingName')))->toBe(["Gridfirst Gridlast{$order['token']}"]);

        $creditMemos = apiGet("/api/rest/v2/orders/{$order['orderId']}/credit-memos", adminToken());
        expect(array_unique(array_column(getItems($creditMemos), 'orderIncrementId')))->toBe([$order['orderIncrementId']]);
    });
});
