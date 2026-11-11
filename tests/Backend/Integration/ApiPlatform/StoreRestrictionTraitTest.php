<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\StoreRestrictionTrait;

uses(Tests\MahoBackendTestCase::class);

/**
 * Each API class asks StoreRestrictionTrait whether the token can use a store or a website that the client names,
 * so that the rule has one spelling and one message (#1348).
 */

function storeRestrictionGate(): object
{
    return new class {
        use StoreRestrictionTrait {
            assertRequestedStoreAllowed as public;
            assertRequestedWebsiteAllowed as public;
            assertUnrestricted as public;
        }
    };
}

function storeRestrictionUser(?array $allowedStoreIds): ApiUser
{
    return new ApiUser('service', ['ROLE_API_USER'], apiUserId: 1, allowedStoreIds: $allowedStoreIds);
}

it('refuses a store that the token cannot use, with one message', function () {
    $gate = storeRestrictionGate();
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $otherStoreId = $storeId + 1000;

    $gate->assertRequestedStoreAllowed($storeId, storeRestrictionUser([$storeId]));
    $gate->assertRequestedStoreAllowed($otherStoreId, storeRestrictionUser(null));
    $gate->assertRequestedStoreAllowed($otherStoreId, null);

    expect(fn() => $gate->assertRequestedStoreAllowed($otherStoreId, storeRestrictionUser([$storeId])))
        ->toThrow(Mage_Core_Exception_Forbidden::class, "Token is not authorized for store: {$otherStoreId}");
});

it('refuses a website that has no store of the token', function () {
    $gate = storeRestrictionGate();
    $store = Mage::app()->getDefaultStoreView();
    $websiteId = (int) $store->getWebsiteId();
    $otherWebsiteId = $websiteId + 1000;
    $restricted = storeRestrictionUser([(int) $store->getId()]);

    $gate->assertRequestedWebsiteAllowed($websiteId, $restricted);
    $gate->assertRequestedWebsiteAllowed($otherWebsiteId, storeRestrictionUser(null));
    $gate->assertRequestedWebsiteAllowed($otherWebsiteId, null);

    expect(fn() => $gate->assertRequestedWebsiteAllowed($otherWebsiteId, $restricted))
        ->toThrow(Mage_Core_Exception_Forbidden::class, "Token is not authorized for website: {$otherWebsiteId}");
});

it('refuses an action on all stores for a token with a store restriction', function () {
    $gate = storeRestrictionGate();

    $gate->assertUnrestricted(storeRestrictionUser(null));

    expect(fn() => $gate->assertUnrestricted(storeRestrictionUser([1])))
        ->toThrow(Mage_Core_Exception_Forbidden::class, 'This action changes all stores. A token with a store restriction cannot do it.');
});
