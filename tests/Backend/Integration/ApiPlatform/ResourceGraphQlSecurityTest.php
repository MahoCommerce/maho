<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\ApiResource;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Server-independent regression guard for GraphQL read authorization.
 *
 * GraphQL operations are gated only by their per-operation `security:`
 * expression (the storefront /api/graphql firewall is PUBLIC_ACCESS, and
 * API Platform's access checker evaluates the expression for GraphQL just as it
 * does for REST). A read query that omits `security:` is therefore reachable
 * unauthenticated, which is how the
 * gift card item_query leaked full card data (code, balance, recipient PII)
 * before this was fixed. These tests assert the security expressions are
 * present at the attribute level, no running HTTP server required.
 */

beforeEach(function (): void {
    // The Mage\<Module>\Api\ and Maho\<Module>\Api\ classes are autoloaded by the
    // API kernel boot(), which doesn't run in a plain backend test. Map both
    // namespaces to app/code/core/ so the resource attributes resolve here.
    static $registered = false;
    if (!$registered) {
        spl_autoload_register(function (string $class): void {
            if (!str_starts_with($class, 'Mage\\') && !str_starts_with($class, 'Maho\\')) {
                return;
            }
            $file = Mage::getBaseDir() . '/app/code/core/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        $registered = true;
    }
});

/**
 * Collect every GraphQL operation declared on a resource, keyed by name.
 *
 * @return array<string, ApiPlatform\Metadata\GraphQl\Operation>
 */
function graphQlOperationsOf(string $resourceClass): array
{
    $ops = [];
    $attributes = (new ReflectionClass($resourceClass))
        ->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF);

    foreach ($attributes as $attribute) {
        /** @var ApiResource $resource */
        $resource = $attribute->newInstance();
        foreach ($resource->getGraphQlOperations() ?? [] as $operation) {
            $ops[$operation->getName()] = $operation;
        }
    }

    return $ops;
}

it('requires authentication for the gift card item GraphQL query', function (): void {
    $ops = graphQlOperationsOf(Maho\Giftcard\Api\GiftCard::class);

    expect($ops)->toHaveKey('item_query');
    // Authorization is now granular: the role-based ROLE_API_USER check was
    // replaced by the per-resource `giftcards/read` permission (admins keep the
    // ROLE_ADMIN branch). The read must still be non-public to guard the gift
    // card PII leak this test was written for.
    expect($ops['item_query']->getSecurity())
        ->not->toBeNull()
        ->toContain('ROLE_ADMIN')
        ->toContain('giftcards/read');
});

it('keeps the gift card collection GraphQL query privileged', function (): void {
    $ops = graphQlOperationsOf(Maho\Giftcard\Api\GiftCard::class);

    expect($ops['collection_query']->getSecurity())
        ->not->toBeNull()
        ->toContain('ROLE_ADMIN');
});

it('leaves no gift card GraphQL query public except the rate-limited balance check', function (): void {
    $ops = graphQlOperationsOf(Maho\Giftcard\Api\GiftCard::class);

    foreach ($ops as $name => $operation) {
        if (!$operation instanceof ApiPlatform\Metadata\GraphQl\Query
            && !$operation instanceof ApiPlatform\Metadata\GraphQl\QueryCollection) {
            continue;
        }

        // checkGiftcardBalance is intentionally public (guests verify a card at
        // checkout) but is rate-limited and returns a minimal, PII-free DTO.
        if ($name === 'checkGiftcardBalance') {
            continue;
        }

        expect($operation->getSecurity())->not->toBeNull(
            "GraphQL read operation '{$name}' on GiftCard must declare a security expression.",
        );
    }
});

it('requires authentication for the coupon item GraphQL query', function (): void {
    $ops = graphQlOperationsOf(Mage\SalesRule\Api\Coupon::class);

    expect($ops['item_query']->getSecurity())
        ->not->toBeNull()
        ->toContain('ROLE_ADMIN');
});

it('never serializes the review author customer id in responses', function (): void {
    $attributes = (new ReflectionProperty(Mage\Review\Api\Review::class, 'customerId'))
        ->getAttributes(ApiPlatform\Metadata\ApiProperty::class);

    expect($attributes)->not->toBeEmpty();

    $apiProperty = $attributes[0]->newInstance();
    expect($apiProperty->isReadable())->toBeFalse();
    expect($apiProperty->isWritable())->toBeFalse();
});
