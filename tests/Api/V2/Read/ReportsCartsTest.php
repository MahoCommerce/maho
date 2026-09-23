<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ReportFixture;

/**
 * API v2 shopping cart reports: products in carts and abandoned carts.
 *
 * @group read
 */

beforeAll(function (): void {
    ReportFixture::snapshot();
    $product = ReportFixture::createProduct('report-carts', 12.0);
    $customer = ReportFixture::createCustomer('carts');
    $GLOBALS['reportCarts'] = [
        'product' => $product,
        'customer' => $customer,
        'guest' => ReportFixture::createCart($product, 2),
        'customerCart' => ReportFixture::createCart($product, 1, $customer),
    ];
});

afterAll(function (): void {
    ReportFixture::restore();
    cleanupTestData();
});

/**
 * @return array<int, array<string, mixed>> the rows of all pages, by $key
 */
function reportCartsRows(string $path, string $key, string $query = ''): array
{
    $rows = [];
    for ($page = 1; $page <= 50; $page++) {
        $response = apiGet('/api/rest/v2/reports/carts/' . $path . '?pageSize=100&page=' . $page . $query, adminToken());
        expect($response['status'])->toBe(200);
        foreach ($response['json']['member'] as $row) {
            $rows[$row[$key]] = $row;
        }
        if ($page * 100 >= $response['json']['totalItems']) {
            break;
        }
    }
    return $rows;
}

describe('Shopping cart reports', function (): void {

    it('counts the active carts of each product', function (): void {
        $product = $GLOBALS['reportCarts']['product'];
        $rows = reportCartsRows('products', 'productId');
        expect($rows[(int) $product->getId()] ?? null)->toBe([
            'productId' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => 12,
            'carts' => 2,
            'orders' => 0,
        ]);

        $otherStores = array_values(array_diff(array_keys(Mage::app()->getStores()), [1]));
        if ($otherStores !== []) {
            expect(reportCartsRows('products', 'productId', '&storeId=' . $otherStores[0]))->not->toHaveKey((int) $product->getId());
        }
    });

    it('lists the abandoned carts with their customer and subtotal', function (): void {
        ['guest' => $guest, 'customerCart' => $customerCart, 'customer' => $customer] = $GLOBALS['reportCarts'];
        $rows = reportCartsRows('abandoned', 'cartId');
        expect($rows)->toHaveKey((int) $guest->getId())
            ->and($rows[(int) $guest->getId()]['customerId'])->toBeNull()
            ->and($rows[(int) $guest->getId()]['itemsQty'])->toBe(2)
            ->and((float) $rows[(int) $guest->getId()]['subtotal'])->toEqual(24.0)
            ->and($rows[(int) $customerCart->getId()]['customerId'])->toBe((int) $customer->getId())
            ->and($rows[(int) $customerCart->getId()]['customerName'])->toBe('Report Carts')
            ->and($rows[(int) $customerCart->getId()]['customerEmail'])->toBe($customer->getEmail());

        $customersOnly = reportCartsRows('abandoned', 'cartId', '&customersOnly=true');
        expect($customersOnly)->toHaveKey((int) $customerCart->getId())
            ->and($customersOnly)->not->toHaveKey((int) $guest->getId());

        $first = apiGet('/api/rest/v2/reports/carts/abandoned?pageSize=1', adminToken())['json'];
        expect($first['report'])->toBe('carts-abandoned')
            ->and($first['currency'])->toBe(Mage::app()->getBaseCurrencyCode())
            ->and($first['member'])->toHaveCount(1);
    });

    it('applies the admin ACL of each report', function (): void {
        $token = adminTokenWithAcl(['admin/report/shopcart/product'], 'apitest_reports_carts');
        expect(apiGet('/api/rest/v2/reports/carts/products', $token)['status'])->toBe(200)
            ->and(apiGet('/api/rest/v2/reports/carts/abandoned', $token)['status'])->toBe(403);
        expect(apiGet('/api/rest/v2/reports/carts/abandoned', serviceToken(['reports/read']))['status'])->toBe(200);
    });
});
