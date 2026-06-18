<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Security Hardening Regression Tests
 *
 * Covers the authorization / information-disclosure fixes:
 * - Wishlist move-to-cart must not write into another customer's cart (IDOR).
 * - Public coupon validation must not disclose discount details to anonymous callers.
 * - Guest newsletter subscribe must not reveal whether an address already exists.
 * - Public review listings must not expose the author's internal customer id.
 * - The public gift card balance check must not leak recipient/sender PII.
 *
 * @group write
 */

use Tests\Helpers\ApiV2Helper;

afterAll(function (): void {
    cleanupTestData();
});

describe('Wishlist move-to-cart ownership (IDOR)', function (): void {

    it('refuses to move an item into another customer\'s cart', function (): void {
        ApiV2Helper::ensureMahoBootstrapped();

        // A cart that belongs to a different customer than the caller.
        $victimCustomerId = (int) fixtures('customer_id') + 100000;
        $victimQuote = Mage::getModel('sales/quote');
        $victimQuote->setStoreId((int) Mage::app()->getStore()->getId());
        $victimQuote->setCustomerId($victimCustomerId);
        $victimQuote->setIsActive(1);
        $victimQuote->save();
        $victimQuoteId = (int) $victimQuote->getId();
        trackCreated('quote', $victimQuoteId);

        // Caller adds an item to their own wishlist.
        $add = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => fixtures('product_id'),
            'qty' => 1,
        ], customerToken());
        expect($add['status'])->toBeSuccessful();
        $itemId = (int) $add['json']['id'];
        trackCreated('wishlist_item', $itemId);

        // Attempt to move that item into the victim's cart by numeric id.
        $move = apiPost("/api/rest/v2/customers/me/wishlist/{$itemId}/move-to-cart", [
            'qty' => 1,
            'cartId' => $victimQuoteId,
        ], customerToken());

        expect($move['status'])->toBeForbidden();

        // The victim's cart must remain empty.
        $reloaded = Mage::getModel('sales/quote')->loadByIdWithoutStore($victimQuoteId);
        expect((int) $reloaded->getItemsCount())->toBe(0);
    });

});

describe('Coupon validation disclosure', function (): void {

    it('hides discount details from anonymous callers but shows them to admins', function (): void {
        ApiV2Helper::ensureMahoBootstrapped();

        $code = 'SECTEST' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $rule = Mage::getModel('salesrule/rule');
        $rule->setName('Security test rule')
            ->setIsActive(1)
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(15)
            ->setDiscountStep(0)
            ->setStopRulesProcessing(0)
            ->setCustomerGroupIds(Mage::getModel('customer/group')->getCollection()->getAllIds())
            ->setWebsiteIds([Mage::app()->getStore()->getWebsiteId()])
            ->save();

        $coupon = Mage::getModel('salesrule/coupon');
        $coupon->setRuleId($rule->getId())->setCode($code)->setType(0)->save();

        // Anonymous: yes/no only, no discount value.
        $anon = apiPost('/api/rest/v2/coupons/validate', ['code' => $code]);
        expect($anon['status'])->toBeSuccessful();
        expect($anon['json']['isValid'] ?? null)->toBeTrue();
        expect($anon['json']['discountAmount'] ?? null)->toBeNull();
        expect($anon['json']['discountType'] ?? null)->toBeNull();

        // Admin: full result including the discount value.
        $admin = apiPost('/api/rest/v2/coupons/validate', ['code' => $code], adminToken());
        expect($admin['status'])->toBeSuccessful();
        expect($admin['json']['isValid'] ?? null)->toBeTrue();
        expect((float) ($admin['json']['discountAmount'] ?? 0))->toBeGreaterThan(0);

        // Cleanup
        Mage::register('isSecureArea', true, true);
        $coupon->delete();
        $rule->delete();
        Mage::unregister('isSecureArea');
    });

});

describe('Newsletter guest subscribe disclosure', function (): void {

    it('returns a uniform response without leaking customer id', function (): void {
        ApiV2Helper::ensureMahoBootstrapped();

        // Guest subscription must be allowed for this path to be exercised.
        Mage::getModel('core/config')->saveConfig(
            Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG,
            '1',
            'default',
            0,
        );
        Mage::app()->getCache()->cleanType('config');

        $email = 'guestsub_' . bin2hex(random_bytes(4)) . '@example.com';

        $response = apiPost('/api/rest/v2/newsletter/subscribe', ['email' => $email]);

        expect($response['status'])->toBeSuccessful();
        // A guest response must never carry the subscriber's internal customer id.
        expect($response['json']['customerId'] ?? null)->toBeNull();
    });

});

describe('Review listing disclosure', function (): void {

    it('does not expose the author customer id in public review listings', function (): void {
        $productId = fixtures('product_id');

        $response = apiGet("/api/rest/v2/products/{$productId}/reviews");

        expect($response['status'])->toBeIn([200, 404]);

        foreach (getItems($response) as $review) {
            expect($review)->not->toHaveKey('customerId');
        }
    });

});

describe('Gift card balance disclosure (GraphQL)', function (): void {

    it('returns balance without recipient/sender PII', function (): void {
        ApiV2Helper::ensureMahoBootstrapped();

        if (!class_exists('Maho_Giftcard_Model_Giftcard')) {
            $this->markTestSkipped('Gift card module not available');
        }

        $giftcard = Mage::getModel('giftcard/giftcard');
        $giftcard->setData('recipient_email', 'victim@example.com');
        $giftcard->setData('recipient_name', 'Victim');
        $giftcard->setData('sender_email', 'sender@example.com');
        $giftcard->setData('message', 'secret message');
        $giftcard->setData('balance', 50);
        $giftcard->setData('initial_balance', 50);
        $giftcard->save();
        $code = (string) $giftcard->getCode();

        if ($code === '') {
            $this->markTestSkipped('Gift card has no code to query');
        }

        $query = <<<'GRAPHQL'
        query ($code: String!) {
            checkGiftcardBalance(code: $code) {
                balance
                recipientEmail
                senderEmail
                message
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, ['code' => $code]);
        $data = $response['json']['data']['checkGiftcardBalance'] ?? null;

        if ($data !== null) {
            expect($data['recipientEmail'] ?? null)->toBeNull();
            expect($data['senderEmail'] ?? null)->toBeNull();
            expect($data['message'] ?? null)->toBeNull();
        }

        Mage::register('isSecureArea', true, true);
        $giftcard->delete();
        Mage::unregister('isSecureArea');
    });

});
