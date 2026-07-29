<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Maho\ApiPlatform\Security\OperationAccessChecker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces Maho admin ACL on every API Platform request made with an admin
 * token.
 *
 * Mirrors the backend's `Mage_Adminhtml_Controller_Action::ADMIN_RESOURCE`
 * pattern. Every admin-reachable API resource class declares the same
 * constant the matching backend controller uses, e.g.
 *
 *     class Order extends CrudResource {
 *         public const ADMIN_RESOURCE = Mage_Adminhtml_Sales_OrderController::ADMIN_RESOURCE;
 *     }
 *
 * The listener calls Mage::getSingleton('admin/session')->isAllowed() before
 * the controller runs, exactly as `Mage_Adminhtml_Controller_Action::_isAllowed()`
 * does for backend pages.
 *
 * Default-deny policy: when an admin token reaches a resource class that has
 * no ADMIN_RESOURCE constant, the listener throws AccessDeniedHttpException.
 * This forces every admin-reachable endpoint to be conscious about ACL, the
 * same mistake that produced the original Catalog-Editor-can-issue-refunds
 * bypass cannot recur silently.
 *
 * Non-admin tokens (customer, API user) bypass this listener, they're gated
 * by each operation's `security:` expression, which API Platform evaluates for
 * REST and GraphQL alike and routes to ApiUserVoter.
 *
 * The rules themselves live in {@see OperationAccessChecker}; this listener is
 * the HTTP binding of them. MCP dispatches many operations inside one POST and
 * so never sets `_api_resource_class`, and applies the same checker from
 * {@see \Maho\ApiPlatform\State\McpAclProvider} instead.
 *
 * Priority 4: after the firewall (8), after StoreContextAuthorizationListener
 * (6), and before any controller code runs.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
class AdminAclListener
{
    public function __construct(
        private readonly OperationAccessChecker $accessChecker,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only API Platform routes (resource_class is set by the API Platform
        // routing). The /api/admin/graphql controller dispatches multiple
        // operations within one request and is gated separately by per-handler
        // AdminAcl::checkResource() calls.
        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');
        if (!is_string($resourceClass) || $resourceClass === '' || !is_string($operationName)) {
            return;
        }

        try {
            $operation = $this->resourceMetadataFactory->create($resourceClass)->getOperation($operationName);
        } catch (\Throwable) {
            // An operation we can't resolve can't be proven public either, and
            // the checker treats null as non-public rather than waving it through.
            $operation = null;
        }

        $this->accessChecker->checkAdminAcl($resourceClass, $operation);
    }
}
