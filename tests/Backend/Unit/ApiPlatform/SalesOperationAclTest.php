<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\GraphQl\AdminAclProvider;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Maho\Config\ApiResource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

uses(Tests\MahoBackendTestCase::class);

/**
 * @return array<string, Operation>
 */
function salesOperationAclOperations(string $resourceClass): array
{
    $operations = [];
    foreach (new ReflectionClass($resourceClass)->getAttributes(ApiResource::class) as $attribute) {
        $resource = $attribute->newInstance();
        foreach ([$resource->getOperations() ?? [], $resource->getGraphQlOperations() ?? []] as $list) {
            foreach ($list as $operation) {
                $operations[$operation->getName() ?? $operation->getUriTemplate() ?? ''] = $operation;
            }
        }
    }
    return $operations;
}

it('gives each order and document action the ACL path of the admin order action', function (string $resourceClass, string $operationName, string $aclPath): void {
    $operation = salesOperationAclOperations($resourceClass)[$operationName] ?? null;

    expect($operation)->not->toBeNull()
        ->and(OperationAccessChecker::resolveOperationResource($operation))->toBe($aclPath);
})->with([
    'invoice create' => [Mage\Sales\Api\Invoice::class, 'order_invoice_create', 'sales/order/actions/invoice'],
    'invoice capture' => [Mage\Sales\Api\Invoice::class, 'invoice_capture', 'sales/order/actions/capture'],
    'invoice void' => [Mage\Sales\Api\Invoice::class, 'invoice_void', 'sales/order/actions/cancel'],
    'invoice cancel' => [Mage\Sales\Api\Invoice::class, 'invoice_cancel', 'sales/order/actions/cancel'],
    'shipment create (REST)' => [Mage\Sales\Api\Shipment::class, '/orders/{orderId}/shipments', 'sales/order/actions/ship'],
    'shipment create (GraphQL)' => [Mage\Sales\Api\Shipment::class, 'create', 'sales/order/actions/ship'],
    'credit memo create (REST)' => [Mage\Sales\Api\CreditMemo::class, '/orders/{orderId}/credit-memos', 'sales/order/actions/creditmemo'],
    'credit memo create (GraphQL)' => [Mage\Sales\Api\CreditMemo::class, 'create', 'sales/order/actions/creditmemo'],
    'order place' => [Mage\Sales\Api\Order::class, 'place_order', 'sales/order/actions/create'],
    'order place from cart' => [Mage\Sales\Api\Order::class, 'place_customer_order', 'sales/order/actions/create'],
    'order place (GraphQL)' => [Mage\Sales\Api\Order::class, 'place', 'sales/order/actions/create'],
    'order hold' => [Mage\Sales\Api\Order::class, 'order_hold', 'sales/order/actions/hold'],
    'order hold (GraphQL)' => [Mage\Sales\Api\Order::class, 'hold', 'sales/order/actions/hold'],
    'order unhold' => [Mage\Sales\Api\Order::class, 'order_unhold', 'sales/order/actions/unhold'],
    'order unhold (GraphQL)' => [Mage\Sales\Api\Order::class, 'unhold', 'sales/order/actions/unhold'],
    'order cancel' => [Mage\Sales\Api\Order::class, 'order_cancel', 'sales/order/actions/cancel'],
    'order cancel (GraphQL)' => [Mage\Sales\Api\Order::class, 'cancel', 'sales/order/actions/cancel'],
    'order comment' => [Mage\Sales\Api\Order::class, 'order_add_comment', 'sales/order/actions/comment'],
    'order comment (GraphQL)' => [Mage\Sales\Api\Order::class, 'addComment', 'sales/order/actions/comment'],
]);

it('reads no ACL path from an operation that declares none', function (): void {
    expect(OperationAccessChecker::resolveOperationResource(null))->toBeNull()
        ->and(OperationAccessChecker::resolveOperationResource(salesOperationAclOperations(Mage\Sales\Api\Invoice::class)['invoice_get']))->toBeNull();
});

it('checks the admin ACL of each GraphQL operation before the read', function (): void {
    $operation = salesOperationAclOperations(Mage\Sales\Api\Order::class)['hold'];
    $admin = new ApiUser('admin', ['ROLE_ADMIN'], adminId: 1);
    $tokenStorage = new TokenStorage();
    $tokenStorage->setToken(new PreAuthenticatedToken($admin, 'api', $admin->getRoles()));
    $inner = new class implements ProviderInterface {
        public bool $called = false;

        public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
        {
            $this->called = true;
            return null;
        }
    };

    // The test has no admin session, so the checker refuses the admin token
    expect(fn() => new AdminAclProvider($inner, new OperationAccessChecker($tokenStorage))->provide($operation))
        ->toThrow(AccessDeniedHttpException::class)
        ->and($inner->called)->toBeFalse();
});
