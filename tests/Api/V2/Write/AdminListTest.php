<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 admin list tests: customer search and filters, product lists with every
 * status and visibility, and inactive categories.
 *
 * @group write
 */

use Tests\Helpers\ApiV2Helper;

const ADMIN_LIST_EMAIL_PREFIX = 'pest-admin-list-';

/**
 * A customer in group 2 of website 1, with a default billing address.
 *
 * @return array{id: int, token: string, email: string, telephone: string}
 */
function adminListCustomer(): array
{
    static $customer = null;
    if ($customer !== null) {
        return $customer;
    }

    ApiV2Helper::ensureMahoBootstrapped();
    $token = strtolower(substr(bin2hex(random_bytes(6)), 0, 10));
    $email = ADMIN_LIST_EMAIL_PREFIX . $token . '@example.test';

    $model = Mage::getModel('customer/customer');
    $model->setWebsiteId(1)
        ->setStoreId(1)
        ->setGroupId(2)
        ->setFirstname('Zanfirst' . $token)
        ->setLastname('Zanlast' . $token)
        ->setEmail($email)
        ->save();

    $address = Mage::getModel('customer/address');
    $address->setCustomerId($model->getId())
        ->setFirstname('Zanfirst' . $token)
        ->setLastname('Zanlast' . $token)
        ->setStreet('1 Test Street')
        ->setCity('Testville')
        ->setPostcode('12345')
        ->setCountryId('US')
        ->setRegionId(12)
        ->setTelephone('0398' . substr((string) hexdec(substr($token, 0, 6)), 0, 6))
        ->setIsDefaultBilling(true)
        ->save();

    $customer = ['id' => (int) $model->getId(), 'token' => $token, 'email' => $email, 'telephone' => (string) $address->getTelephone()];
    return $customer;
}

/**
 * A disabled simple product, a not-visible virtual product and a category that holds both.
 *
 * @return array{token: string, disabled: int, hidden: int, category: int}
 */
function adminListCatalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    ApiV2Helper::ensureMahoBootstrapped();
    $token = strtolower(substr(bin2hex(random_bytes(6)), 0, 10));
    $rootId = (int) Mage::app()->getStore(1)->getRootCategoryId();
    $root = Mage::getModel('catalog/category')->load($rootId);

    $category = Mage::getModel('catalog/category');
    $category->setStoreId(0)
        ->setName('Pest Inactive ' . $token)
        ->setUrlKey('pest-inactive-' . $token)
        ->setIsActive(0)
        ->setIncludeInMenu(1)
        ->setPath($root->getPath())
        ->save();
    trackCreated('category', (int) $category->getId());

    $create = function (string $name, string $type, int $status, int $visibility) use ($token, $category): int {
        $product = Mage::getModel('catalog/product');
        $product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setSku('PEST-ADMLIST-' . strtoupper($name) . '-' . $token)
            ->setName('Pest Admin List ' . $name . ' ' . $token)
            ->setPrice(10)
            ->setStatus($status)
            ->setVisibility($visibility)
            ->setTypeId($type)
            ->setAttributeSetId(4)
            ->setTaxClassId(0)
            ->setWebsiteIds([1])
            ->setCategoryIds([(int) $category->getId()])
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 5, 'is_in_stock' => 1])
            ->save();
        trackCreated('product', (int) $product->getId());
        return (int) $product->getId();
    };

    $catalog = [
        'token' => $token,
        'disabled' => $create('Disabled', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, Mage_Catalog_Model_Product_Status::STATUS_DISABLED, Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH),
        'hidden' => $create('Hidden', Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL, Mage_Catalog_Model_Product_Status::STATUS_ENABLED, Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE),
        'category' => (int) $category->getId(),
    ];
    Mage::app()->cleanCache();
    return $catalog;
}

function adminListIds(array $response): array
{
    return array_map(intval(...), array_column($response['json']['member'] ?? [], 'id'));
}

afterAll(function (): void {
    cleanupTestData();
    try {
        Mage::getSingleton('core/resource')->getConnection('core_write')
            ->delete('customer_entity', ['email LIKE ?' => ADMIN_LIST_EMAIL_PREFIX . '%']);
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('GET /api/rest/v2/customers search', function (): void {

    it('matches every word in any field, as the admin customer grid does', function (): void {
        $customer = adminListCustomer();
        $search = urlencode("Zanfirst{$customer['token']} Zanlast{$customer['token']}");

        $response = apiGet("/api/rest/v2/customers?search={$search}", adminToken());

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toBe([$customer['id']]);
    });

    it('ignores case and finds short parts of a field', function (): void {
        $customer = adminListCustomer();
        $part = strtoupper(substr($customer['token'], 2, 4));

        $response = apiGet('/api/rest/v2/customers?itemsPerPage=100&search=' . urlencode("ZANLAST {$part}"), adminToken());

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toContain($customer['id']);
    });

    it('finds a customer by part of the email or of the telephone', function (): void {
        $customer = adminListCustomer();

        $byEmail = apiGet('/api/rest/v2/customers?search=' . urlencode("admin-list-{$customer['token']}@"), adminToken());
        expect(adminListIds($byEmail))->toBe([$customer['id']]);

        $byPhone = apiGet('/api/rest/v2/customers?search=' . urlencode(substr($customer['telephone'], 2) . " {$customer['token']}"), adminToken());
        expect(adminListIds($byPhone))->toBe([$customer['id']]);
    });

    it('returns nothing when one word does not match', function (): void {
        $customer = adminListCustomer();

        $response = apiGet('/api/rest/v2/customers?search=' . urlencode("Zanfirst{$customer['token']} zzqx-no-match"), adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['member'])->toBe([]);
        expect($response['json']['totalItems'])->toBe(0);
    });

    it('filters by groupId and websiteId', function (): void {
        $customer = adminListCustomer();
        $search = urlencode("Zanfirst{$customer['token']}");

        expect(adminListIds(apiGet("/api/rest/v2/customers?search={$search}&groupId=2&websiteId=1", adminToken())))
            ->toBe([$customer['id']]);
        expect(adminListIds(apiGet("/api/rest/v2/customers?search={$search}&groupId=3", adminToken())))->toBe([]);
        expect(adminListIds(apiGet("/api/rest/v2/customers?search={$search}&websiteId=999", adminToken())))->toBe([]);
        expect(apiGet('/api/rest/v2/customers?groupId=abc', adminToken())['status'])->toBe(400);
    });

    it('uses only the first words of a long search', function (): void {
        $customer = adminListCustomer();
        $words = array_fill(0, \Mage\Customer\Api\CustomerService::MAX_SEARCH_WORDS, "Zanfirst{$customer['token']}");
        $words[] = 'zzqx-no-match';

        $response = apiGet('/api/rest/v2/customers?search=' . urlencode(implode(' ', $words)), adminToken());

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toBe([$customer['id']]);
    });

    it('rejects a list where a filter takes one value', function (string $query, string $name): void {
        $response = apiGet("/api/rest/v2/customers?{$query}", adminToken());

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? $response['json']['detail'] ?? '')->toContain($name);
    })->with([
        'search' => ['search[]=x', 'search'],
        'email' => ['email[]=a@example.com', 'email'],
        'telephone' => ['telephone[]=0398', 'telephone'],
        'groupId' => ['groupId[]=1', 'groupId'],
    ]);

    it('gives service tokens with customers/read the same search', function (): void {
        $customer = adminListCustomer();

        $response = apiGet('/api/rest/v2/customers?search=' . urlencode("Zanfirst{$customer['token']}"), serviceToken(['customers/read']));

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toBe([$customer['id']]);
    });

    it('returns and reports the requested page size above 30', function (): void {
        $token = strtolower(substr(bin2hex(random_bytes(6)), 0, 10));
        for ($i = 0; $i < 41; $i++) {
            Mage::getModel('customer/customer')
                ->setWebsiteId(1)
                ->setStoreId(1)
                ->setFirstname('Pagefirst')
                ->setLastname('Pagelast' . $token)
                ->setEmail(ADMIN_LIST_EMAIL_PREFIX . "page-{$i}-{$token}@example.test")
                ->save();
        }
        $search = urlencode('Pagelast' . $token);

        $first = apiGet("/api/rest/v2/customers?itemsPerPage=40&search={$search}", adminToken());
        expect($first['status'])->toBe(200);
        expect($first['json']['totalItems'])->toBe(41);
        expect($first['json']['member'])->toHaveCount(40);
        expect($first['json']['view']['last'] ?? '')->toContain('page=2');

        $second = apiGet("/api/rest/v2/customers?itemsPerPage=40&page=2&search={$search}", adminToken());
        expect($second['json']['member'])->toHaveCount(1);
    });
});

describe('GET /api/rest/v2/products for backend callers', function (): void {

    it('lists disabled and not-visible products and filters by part of the SKU', function (): void {
        $catalog = adminListCatalog();

        $response = apiGet('/api/rest/v2/products?sku=' . urlencode($catalog['token']), adminToken());
        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toEqualCanonicalizing([$catalog['disabled'], $catalog['hidden']]);
    });

    it('filters by status and type', function (): void {
        $catalog = adminListCatalog();
        $sku = urlencode($catalog['token']);

        $disabled = apiGet("/api/rest/v2/products?sku={$sku}&status=disabled", adminToken());
        expect(adminListIds($disabled))->toBe([$catalog['disabled']]);
        expect($disabled['json']['member'][0]['status'])->toBe('disabled');

        expect(adminListIds(apiGet("/api/rest/v2/products?sku={$sku}&status=enabled", adminToken())))->toBe([$catalog['hidden']]);
        expect(adminListIds(apiGet("/api/rest/v2/products?sku={$sku}&type=virtual", adminToken())))->toBe([$catalog['hidden']]);
        expect(apiGet('/api/rest/v2/products?status=archived', adminToken())['status'])->toBe(400);
    });

    it('searches every word in the name or the SKU, including hidden products', function (): void {
        $catalog = adminListCatalog();

        $response = apiGet('/api/rest/v2/products?search=' . urlencode("admin list {$catalog['token']}"), adminToken());
        expect(adminListIds($response))->toEqualCanonicalizing([$catalog['disabled'], $catalog['hidden']]);

        $response = apiGet('/api/rest/v2/products?search=' . urlencode("PEST-ADMLIST-HIDDEN {$catalog['token']}"), adminToken());
        expect(adminListIds($response))->toBe([$catalog['hidden']]);
    });

    it('uses only the first words of a long search', function (): void {
        $catalog = adminListCatalog();
        $words = array_fill(0, \Mage\Catalog\Api\ProductProvider::MAX_SEARCH_WORDS, $catalog['token']);
        $words[] = 'zzqx-no-match';

        $response = apiGet('/api/rest/v2/products?search=' . urlencode(implode(' ', $words)), adminToken());

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toEqualCanonicalizing([$catalog['disabled'], $catalog['hidden']]);
    });

    it('rejects a list where a filter takes one value', function (string $query, string $name): void {
        $response = apiGet("/api/rest/v2/products?{$query}", adminToken());

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? $response['json']['detail'] ?? '')->toContain($name);
    })->with([
        'search' => ['search[]=x', 'search'],
        'sku' => ['sku[]=x', 'sku'],
        'status' => ['status[]=enabled', 'status'],
        'type' => ['type[]=simple', 'type'],
        'categoryId' => ['categoryId[]=3', 'categoryId'],
    ]);

    it('lists the products assigned to a category, whatever their status', function (): void {
        $catalog = adminListCatalog();

        $response = apiGet("/api/rest/v2/products?categoryId={$catalog['category']}", serviceToken(['products/read']));

        expect($response['status'])->toBe(200);
        expect(adminListIds($response))->toEqualCanonicalizing([$catalog['disabled'], $catalog['hidden']]);
    });

    it('keeps the public list to enabled, visible products', function (): void {
        $catalog = adminListCatalog();

        $response = apiGet('/api/rest/v2/products?itemsPerPage=100&status=disabled&sku=' . urlencode($catalog['token']));
        expect($response['status'])->toBe(200);
        foreach ($response['json']['member'] as $product) {
            expect($product['status'])->toBe('enabled');
        }

        $response = apiGet("/api/rest/v2/products?categoryId={$catalog['category']}", customerToken());
        expect(adminListIds($response))->toBe([]);
    });
});

describe('GET /api/rest/v2/categories for backend callers', function (): void {

    it('returns an inactive category to an admin, and 404 to a guest', function (): void {
        $catalog = adminListCatalog();

        $admin = apiGet("/api/rest/v2/categories/{$catalog['category']}", adminToken());
        expect($admin['status'])->toBe(200);
        expect($admin['json']['isActive'])->toBeFalse();

        expect(apiGet("/api/rest/v2/categories/{$catalog['category']}")['status'])->toBe(404);
    });

    it('lists inactive categories only to backend callers', function (): void {
        $catalog = adminListCatalog();
        $path = '/api/rest/v2/categories?itemsPerPage=500&search=' . urlencode("Pest Inactive {$catalog['token']}");

        expect(adminListIds(apiGet($path, adminToken())))->toBe([$catalog['category']]);
        expect(adminListIds(apiGet($path, serviceToken(['categories/read']))))->toBe([$catalog['category']]);
        expect(adminListIds(apiGet($path)))->toBe([]);
    });
});
