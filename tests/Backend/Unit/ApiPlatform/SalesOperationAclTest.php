<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ApiPlatform\Mcp\Security\ElementAccessCheckerInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactoryInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\GraphQl\AdminAclProvider;
use Maho\ApiPlatform\Mcp\PermissionElementAccessChecker;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Maho\Config\ApiResource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Tests\Helpers\AdminRoleFixture;

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

/**
 * Run the operation $operationName of the Order resource through AdminAclProvider for an admin token, and return true when the read ran.
 */
function salesOperationAclProvide(string $operationName): bool
{
    $admin = new ApiUser('admin', ['ROLE_ADMIN'], adminId: (int) Mage::getSingleton('admin/session')->getUser()->getId());
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
    new AdminAclProvider($inner, new OperationAccessChecker($tokenStorage))
        ->provide(salesOperationAclOperations(Mage\Sales\Api\Order::class)[$operationName]->withClass(Mage\Sales\Api\Order::class));
    return $inner->called;
}

it('checks the ACL path of each GraphQL operation before the read', function (string $operationName, string $aclPath): void {
    $user = AdminRoleFixture::login(['admin/sales', 'admin/sales/order']);
    try {
        expect(fn() => salesOperationAclProvide($operationName))
            ->toThrow(AccessDeniedHttpException::class, $aclPath);
    } finally {
        AdminRoleFixture::delete($user);
    }

    $user = AdminRoleFixture::login(['admin/sales', 'admin/sales/order', 'admin/sales/order/actions', 'admin/' . $aclPath]);
    try {
        expect(salesOperationAclProvide($operationName))->toBeTrue();
    } finally {
        AdminRoleFixture::delete($user);
    }
})->with([
    'hold' => ['hold', 'sales/order/actions/hold'],
    'place order, a public operation' => ['place', 'sales/order/actions/create'],
]);

it('hides the MCP guest order tool from an admin without the Create order action', function (): void {
    $operation = salesOperationAclOperations(Mage\Sales\Api\Order::class)['place_guest_order']->withClass(Mage\Sales\Api\Order::class);
    $metadata = $this->createMock(OperationMetadataFactoryInterface::class);
    $metadata->method('create')->willReturn($operation);
    $decorated = $this->createMock(ElementAccessCheckerInterface::class);
    $decorated->method('isGranted')->willReturn(true);
    $isListed = function (bool $asAdmin) use ($metadata, $decorated): bool {
        $tokenStorage = new TokenStorage();
        if ($asAdmin) {
            $admin = new ApiUser('admin', ['ROLE_ADMIN'], adminId: (int) Mage::getSingleton('admin/session')->getUser()->getId());
            $tokenStorage->setToken(new PreAuthenticatedToken($admin, 'api', $admin->getRoles()));
        }
        return new PermissionElementAccessChecker($decorated, $metadata, new OperationAccessChecker($tokenStorage))
            ->isGranted('place_guest_order');
    };

    $user = AdminRoleFixture::login(['admin/sales', 'admin/sales/order']);
    try {
        expect($isListed(true))->toBeFalse()
            ->and($isListed(false))->toBeTrue();
    } finally {
        AdminRoleFixture::delete($user);
    }

    $user = AdminRoleFixture::login(['admin/sales', 'admin/sales/order', 'admin/sales/order/actions', 'admin/sales/order/actions/create']);
    try {
        expect($isListed(true))->toBeTrue();
    } finally {
        AdminRoleFixture::delete($user);
    }
});
