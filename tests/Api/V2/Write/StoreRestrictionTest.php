<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 token store-allowlist enforcement tests.
 *
 * A second website + store view is created so a store-restricted service token
 * can be pointed at a scope that contains none of the seeded data: order reads
 * and mutations must be store-filtered, customer/coupon/gift card reads must be
 * website-filtered, and coupon/gift card creation must default an omitted
 * website to the current store's website instead of all/none.
 *
 * @group write
 */

const RESTRICT_WEBSITE_CODE = 'pest_restrict_w';
const RESTRICT_STORE_CODE = 'pest_restrict_s';

function restrictStoreId(): int
{
    return (int) ($GLOBALS['_restrict_store_id'] ?? 0);
}

function restrictWebsiteId(): int
{
    return (int) ($GLOBALS['_restrict_website_id'] ?? 0);
}

function trackRestrictGiftCard(string $code): void
{
    $GLOBALS['_restrict_gc_codes'][] = $code;
}

/** Collection items regardless of the hydra/plain response shape. */
function restrictMembers(array $response): array
{
    $json = $response['json'];
    return $json['hydra:member'] ?? $json['member'] ?? (array_is_list($json) ? $json : []);
}

beforeAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();

    $website = Mage::getModel('core/website')->load(RESTRICT_WEBSITE_CODE, 'code');
    if (!$website->getId()) {
        $website->setCode(RESTRICT_WEBSITE_CODE)
            ->setName('Pest Restriction Website')
            ->setSortOrder(99)
            ->save();

        $group = Mage::getModel('core/store_group')
            ->setWebsiteId((int) $website->getId())
            ->setName('Pest Restriction Group')
            ->setRootCategoryId((int) Mage::app()->getStore(1)->getRootCategoryId())
            ->save();

        $store = Mage::getModel('core/store')
            ->setCode(RESTRICT_STORE_CODE)
            ->setWebsiteId((int) $website->getId())
            ->setGroupId((int) $group->getId())
            ->setName('Pest Restriction Store')
            ->setIsActive(1)
            ->setSortOrder(99)
            ->save();

        $website->setDefaultGroupId((int) $group->getId())->save();
        $group->setDefaultStoreId((int) $store->getId())->save();
    }

    $GLOBALS['_restrict_website_id'] = (int) $website->getId();
    $GLOBALS['_restrict_store_id'] = (int) Mage::getModel('core/store')
        ->load(RESTRICT_STORE_CODE, 'code')
        ->getId();

    Mage::app()->cleanCache();
});

afterAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();

    foreach ($GLOBALS['_restrict_gc_codes'] ?? [] as $code) {
        try {
            Mage::getSingleton('core/resource')->getConnection('core_write')
                ->delete('giftcard', ['code = ?' => $code]);
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }

    Mage::register('isSecureArea', true);
    try {
        foreach (['core/store' => RESTRICT_STORE_CODE, 'core/website' => RESTRICT_WEBSITE_CODE] as $model => $code) {
            $entity = Mage::getModel($model)->load($code, 'code');
            if ($entity->getId()) {
                $entity->delete();
            }
        }
        $group = Mage::getModel('core/store_group')->load('Pest Restriction Group', 'name');
        if ($group->getId()) {
            $group->delete();
        }
    } catch (\Throwable $e) {
        // Ignore cleanup errors
    } finally {
        Mage::unregister('isSecureArea');
    }

    Mage::app()->cleanCache();
    cleanupTestData();
});

describe('Store-restricted order access', function (): void {

    it('returns an empty order list for a token restricted to a store with no orders', function (): void {
        $token = serviceToken(['orders/read'], [restrictStoreId()]);

        $response = apiGet('/api/rest/v2/orders?store=' . RESTRICT_STORE_CODE, $token);

        expect($response['status'])->toBe(200);
        expect(restrictMembers($response))->toBeArray()->toBeEmpty();
    });

    it('denies reading an order outside the allowlist while an unrestricted token still sees it', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order fixture available');
        }

        $restricted = serviceToken(['orders/read'], [restrictStoreId()]);
        $denied = apiGet("/api/rest/v2/orders/{$orderId}?store=" . RESTRICT_STORE_CODE, $restricted);
        expect($denied['status'])->toBeIn([403, 404]);

        $allowed = apiGet("/api/rest/v2/orders/{$orderId}", serviceToken(['orders/read']));
        expect($allowed['status'])->toBe(200);
    });

    it('denies order mutations outside the allowlist', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order fixture available');
        }

        $restricted = serviceToken(['orders/read', 'orders/write'], [restrictStoreId()]);
        $response = apiPost(
            "/api/rest/v2/orders/{$orderId}/comments?store=" . RESTRICT_STORE_CODE,
            ['comment' => 'must be blocked by the store allowlist'],
            $restricted,
        );

        expect($response['status'])->toBeIn([403, 404]);
    });

});

describe('Store-restricted sales document access', function (): void {

    it('denies invoice reads and lifecycle actions outside the allowlist', function (): void {
        $invoiceId = fixtures('invoice_id');
        if (!$invoiceId) {
            $this->markTestSkipped('No invoice fixture available');
        }
        $orderId = (int) Mage::getModel('sales/order_invoice')->load($invoiceId)->getOrderId();

        $restricted = serviceToken(['invoices/read', 'invoices/write'], [restrictStoreId()]);
        $denied = apiGet("/api/rest/v2/orders/{$orderId}/invoices?store=" . RESTRICT_STORE_CODE, $restricted);
        expect($denied['status'])->toBeIn([403, 404]);

        $cancel = apiPost("/api/rest/v2/invoices/{$invoiceId}/cancel?store=" . RESTRICT_STORE_CODE, [], $restricted);
        expect($cancel['status'])->toBeIn([403, 404]);

        $allowed = apiGet("/api/rest/v2/orders/{$orderId}/invoices", serviceToken(['invoices/read']));
        expect($allowed['status'])->toBe(200);
    });

    it('denies shipment reads outside the allowlist while an unrestricted token still sees it', function (): void {
        $shipment = Mage::getResourceModel('sales/order_shipment_collection')
            ->setOrder('entity_id', 'DESC')->setPageSize(1)->getFirstItem();
        if (!$shipment->getId()) {
            $this->markTestSkipped('No shipments found in database');
        }
        $shipmentId = (int) $shipment->getId();

        $restricted = serviceToken(['shipments/read'], [restrictStoreId()]);
        expect(apiGet("/api/rest/v2/shipments/{$shipmentId}?store=" . RESTRICT_STORE_CODE, $restricted)['status'])->toBeIn([403, 404]);

        expect(apiGet("/api/rest/v2/shipments/{$shipmentId}", serviceToken(['shipments/read']))['status'])->toBe(200);
    });

    it('denies credit memo reads outside the allowlist while an unrestricted token still sees it', function (): void {
        $creditmemo = Mage::getResourceModel('sales/order_creditmemo_collection')
            ->setOrder('entity_id', 'DESC')->setPageSize(1)->getFirstItem();
        if (!$creditmemo->getId()) {
            $this->markTestSkipped('No credit memos found in database');
        }
        $creditmemoId = (int) $creditmemo->getId();

        $restricted = serviceToken(['credit-memos/read'], [restrictStoreId()]);
        expect(apiGet("/api/rest/v2/credit-memos/{$creditmemoId}?store=" . RESTRICT_STORE_CODE, $restricted)['status'])->toBeIn([403, 404]);

        expect(apiGet("/api/rest/v2/credit-memos/{$creditmemoId}", serviceToken(['credit-memos/read']))['status'])->toBe(200);
    });

});

describe('Website-restricted backend product reads', function (): void {

    it('denies a backend product read outside the token website allowlist', function (): void {
        $productId = fixtures('product_id');
        if (!$productId) {
            $this->markTestSkipped('No product fixture available');
        }

        // The fixture product belongs to website 1 only; a products token
        // restricted to the extra website must not load it by id even though
        // backend reads skip the current-store visibility check.
        $restricted = serviceToken(['products/read'], [restrictStoreId()]);
        $denied = apiGet("/api/rest/v2/products/{$productId}?store=" . RESTRICT_STORE_CODE, $restricted);
        expect($denied['status'])->toBeIn([403, 404]);

        expect(apiGet("/api/rest/v2/products/{$productId}", serviceToken(['products/read']))['status'])->toBe(200);
    });

});

describe('Store-restricted product sub-resource writes', function (): void {

    it('denies global-scope media writes for a store-restricted token unless it names an allowlisted store', function (): void {
        $productId = fixtures('product_id');
        if (!$productId) {
            $this->markTestSkipped('No product fixture available');
        }

        // The request-level gate passes (store 1 is the ambient default), but
        // without ?store= the write would resolve the global (store 0) scope.
        $restricted = serviceToken(['products/read', 'products/write'], [1]);
        $denied = apiPut("/api/rest/v2/products/{$productId}/media", ['valueId' => 999999, 'label' => 'x'], $restricted);
        expect($denied['status'])->toBe(403);

        // Naming an allowlisted store passes the guard on to normal validation.
        $storeCode = Mage::app()->getStore(1)->getCode();
        $scoped = apiPut("/api/rest/v2/products/{$productId}/media?store={$storeCode}", ['valueId' => 999999, 'label' => 'x'], $restricted);
        expect($scoped['status'])->not->toBe(403);
    });

});

describe('Store-restricted review access', function (): void {

    it('counts a multi-store review once in the moderation queue total', function (): void {
        $token = serviceToken(['reviews/read'], [1, restrictStoreId()]);

        $before = apiGet('/api/rest/v2/reviews', $token);
        expect($before['status'])->toBe(200);
        $beforeTotal = (int) ($before['json']['hydra:totalItems'] ?? $before['json']['totalItems'] ?? 0);

        $review = Mage::getModel('review/review');
        $review->setEntityId((int) $review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue((int) fixtures('product_id'))
            ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
            ->setTitle('Multi-store moderation count review')
            ->setDetail('Assigned to two stores on the token allowlist.')
            ->setNickname('PestRestrict')
            ->setStoreId(1)
            ->setStores([1, restrictStoreId()])
            ->save();
        trackCreated('review', (int) $review->getId());

        $after = apiGet('/api/rest/v2/reviews', $token);
        expect($after['status'])->toBe(200);
        $afterTotal = (int) ($after['json']['hydra:totalItems'] ?? $after['json']['totalItems'] ?? 0);

        // A review in two allowed stores must not be double-counted.
        expect($afterTotal)->toBe($beforeTotal + 1);
    });

    it('denies reading a review outside the allowlist while an unrestricted token still sees it', function (): void {
        $review = Mage::getModel('review/review');
        $review->setEntityId((int) $review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue((int) fixtures('product_id'))
            ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
            ->setTitle('Store restriction review')
            ->setDetail('Review assigned to the default store only.')
            ->setNickname('PestRestrict')
            ->setStoreId(1)
            ->setStores([1])
            ->save();
        $reviewId = (int) $review->getId();
        trackCreated('review', $reviewId);

        $restricted = serviceToken(['reviews/read'], [restrictStoreId()]);
        $denied = apiGet("/api/rest/v2/reviews/{$reviewId}?store=" . RESTRICT_STORE_CODE, $restricted);
        expect($denied['status'])->toBe(404);

        $allowed = apiGet("/api/rest/v2/reviews/{$reviewId}", serviceToken(['reviews/read']));
        expect($allowed['status'])->toBe(200);
    });

});

describe('Website-restricted coupon access', function (): void {

    it('defaults omitted websiteIds to the current store website on create', function (): void {
        $code = 'PestRstW' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 10,
        ], adminToken());

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $get = apiGet("/api/rest/v2/coupons/{$id}", adminToken());
        expect($get['status'])->toBe(200);
        // Only the current store's website, never the all-websites default
        // (the extra website created by this file would be in it otherwise).
        expect($get['json']['websiteIds'])->toBe([1]);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", adminToken())['status'])->toBeIn([200, 204]);
    });

    it('hides coupons of other websites from restricted tokens', function (): void {
        $code = 'PestRstH' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [restrictWebsiteId()],
        ], adminToken());
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $restricted = serviceToken(['coupons/read'], [1]);
        expect(apiGet("/api/rest/v2/coupons/{$id}", $restricted)['status'])->toBeIn([403, 404]);

        $list = apiGet('/api/rest/v2/coupons?code=' . $code, $restricted);
        expect($list['status'])->toBe(200);
        expect(restrictMembers($list))->toBeEmpty();

        expect(apiGet("/api/rest/v2/coupons/{$id}", adminToken())['status'])->toBe(200);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", adminToken())['status'])->toBeIn([200, 204]);
    });

    it('rejects coupon creation targeting websites outside the allowlist', function (): void {
        $restricted = serviceToken(['coupons/create'], [1]);
        $response = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestRstC' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [restrictWebsiteId()],
        ], $restricted);

        expect($response['status'])->toBe(403);
    });

});

describe('Website-restricted gift card access', function (): void {

    it('defaults an omitted websiteId to the current store website on REST create', function (): void {
        $create = apiPost('/api/rest/v2/giftcards', ['initialBalance' => 10.0], adminToken());

        expect($create['status'])->toBeSuccessful();
        trackRestrictGiftCard($create['json']['code']);
        expect($create['json']['websiteId'])->toBe(1);
    });

    it('defaults an omitted websiteId to the current store website on GraphQL create', function (): void {
        $mutation = <<<'GRAPHQL'
        mutation {
            createGiftCard(input: { initialBalance: 10 }) {
                giftCard {
                    code
                    websiteId
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($mutation, [], adminToken());
        expect($response['json'])->not->toHaveKey('errors');

        $card = $response['json']['data']['createGiftCard']['giftCard'];
        trackRestrictGiftCard($card['code']);
        expect($card['websiteId'])->toBe(1);
    });

    it('hides gift cards of other websites from restricted tokens and the public balance check', function (): void {
        $code = 'PEST-RSTGC-' . time();
        $create = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 25.0,
            'code' => $code,
            'websiteId' => restrictWebsiteId(),
        ], adminToken());
        expect($create['status'])->toBeSuccessful();
        trackRestrictGiftCard($code);
        $id = (int) $create['json']['id'];

        $restricted = serviceToken(['giftcards/read'], [1]);
        expect(apiGet("/api/rest/v2/giftcards/{$id}", $restricted)['status'])->toBeIn([403, 404]);
        expect(apiGet("/api/rest/v2/giftcards/{$id}", adminToken())['status'])->toBe(200);

        // The public balance check runs against the default store (website 1),
        // so a website-2 card must not answer there.
        $balance = gqlQuery('{ checkBalanceGiftCard(code: "' . $code . '") { balance } }');
        expect($balance['json'])->toHaveKey('errors');
    });

    it('rejects gift card creation for a website outside the allowlist', function (): void {
        $restricted = serviceToken(['giftcards/create', 'giftcards/write', 'giftcards/read'], [1]);
        $response = apiPost('/api/rest/v2/giftcards', [
            'initialBalance' => 10.0,
            'websiteId' => restrictWebsiteId(),
        ], $restricted);

        expect($response['status'])->toBe(403);
    });

});

describe('Website-restricted customer access', function (): void {

    it('scopes customer reads to the websites of the token store allowlist', function (): void {
        $customerId = fixtures('customer_id');
        if (!$customerId) {
            $this->markTestSkipped('No customer fixture available');
        }

        $restricted = serviceToken(['customers/read'], [restrictStoreId()]);
        $denied = apiGet("/api/rest/v2/customers/{$customerId}?store=" . RESTRICT_STORE_CODE, $restricted);
        expect($denied['status'])->toBeIn([403, 404]);

        $email = fixtures('customer_email');
        $list = apiGet(
            '/api/rest/v2/customers?email=' . urlencode((string) $email) . '&store=' . RESTRICT_STORE_CODE,
            $restricted,
        );
        expect($list['status'])->toBe(200);
        expect(restrictMembers($list))->toBeEmpty();

        $allowed = apiGet("/api/rest/v2/customers/{$customerId}", serviceToken(['customers/read']));
        expect($allowed['status'])->toBe(200);
    });

});

describe('Store-restricted stock updates', function (): void {

    it('rejects stock writes for products outside the token websites', function (): void {
        $sku = fixtures('product_sku');
        $restricted = serviceToken(['inventory/write'], [restrictStoreId()]);

        $denied = apiPut('/api/rest/v2/inventory?store=' . RESTRICT_STORE_CODE, [
            'sku' => $sku,
            'isInStock' => true,
        ], $restricted);
        expect($denied['status'])->toBe(403);

        $deniedBulk = apiPut('/api/rest/v2/inventory/bulk?store=' . RESTRICT_STORE_CODE, [
            'items' => [['sku' => $sku, 'isInStock' => true]],
        ], $restricted);
        expect($deniedBulk['status'])->toBe(403);
    });

    it('allows an unrestricted token to update the same SKU', function (): void {
        $sku = fixtures('product_sku');

        $response = apiPut('/api/rest/v2/inventory', [
            'sku' => $sku,
            'isInStock' => true,
        ], serviceToken(['inventory/write']));

        expect($response['status'])->toBe(200);
        expect($response['json']['success'])->toBeTrue();
    });

});
