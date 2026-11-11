<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Catalog\Api\GraphQL\ProductQueryHandler;
use Mage\Checkout\Api\GraphQL\CartMutationHandler;
use Mage\Customer\Api\GraphQL\CustomerQueryHandler;
use Mage\Sales\Api\GraphQL\OrderMutationHandler;
use Mage\Sales\Api\OrderProvider;
use Maho\ApiPlatform\Controller\AdminGraphQlController;
use Maho\ApiPlatform\Security\SameOriginGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin GraphQL endpoint gives its variables to the handlers as the client sent them. A variable of the wrong
 * type is an error that names the variable, not a cast or a TypeError.
 */
it('answers a variable of the wrong type with the error of the variable', function (string $method, array $variables, string $message): void {
    $handler = new OrderMutationHandler(new OrderProvider(AdminApiParity::adminSecurity()));

    expect(fn() => $handler->{$method}($variables))->toThrow(NotNormalizableValueException::class, $message);
})->with([
    'a cart id that is a word' => ['handlePlaceOrder', ['cartId' => 'abc'], 'The type of the "cartId" attribute must be "int", "string" given.'],
    'an order number that is a list' => ['handleLookupOrder', ['orderNumber' => ['x']], 'The type of the "orderNumber" attribute must be "string", "array" given.'],
    'a customer id that is a list' => ['handleGetCustomerOrders', ['customerId' => [1]], 'The type of the "customerId" attribute must be "int", "array" given.'],
    'a limit that is a word' => ['handleSearchOrders', ['search' => 'x', 'limit' => 'ten'], 'The type of the "limit" attribute must be "int", "string" given.'],
    'a store id that is a word' => ['handleRecentOrders', ['storeId' => 'abc'], 'The type of the "storeId" attribute must be "int", "string" given.'],
]);

it('answers credit memo items that are not a list with the error of the variable', function (): void {
    $handler = new OrderMutationHandler(new OrderProvider(AdminApiParity::adminSecurity()));

    expect(fn() => $handler->handleCreateCreditMemo(['orderId' => 1, 'items' => 'all'], []))
        ->toThrow(NotNormalizableValueException::class, 'The type of the "items" attribute must be "list", "string" given.');
});

function adminGraphQlResponse(array $body): array
{
    $bare = static fn(string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $controller = new AdminGraphQlController(
        $bare(ProductQueryHandler::class),
        $bare(CartMutationHandler::class),
        new OrderMutationHandler(new OrderProvider(AdminApiParity::adminSecurity())),
        $bare(CustomerQueryHandler::class),
    );
    $request = Request::create(
        '/api/admin/graphql',
        'POST',
        server: ['HTTP_ORIGIN' => SameOriginGuard::allowedOrigins()[0], 'CONTENT_TYPE' => 'application/json'],
        content: json_encode($body, JSON_THROW_ON_ERROR),
    );
    $response = $controller($request);

    return [$response->getStatusCode(), json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)];
}

it('answers a variable of the wrong type with the code BAD_USER_INPUT', function (): void {
    [, $json] = adminGraphQlResponse([
        'query' => 'query lookupOrder { lookupOrder }',
        'operationName' => 'lookupOrder',
        'variables' => ['orderNumber' => ['x']],
    ]);

    expect($json['errors'][0]['extensions']['code'])->toBe('BAD_USER_INPUT')
        ->and($json['errors'][0]['message'])->toContain('"orderNumber"');
});

it('answers variables that are not an object with status 400', function (): void {
    [$status] = adminGraphQlResponse([
        'query' => 'query lookupOrder { lookupOrder }',
        'variables' => 'x',
    ]);

    expect($status)->toBe(400);
});
