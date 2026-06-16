<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho_ApiPlatform_Helper_Data as Helper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gate the new API entry points behind per-protocol admin toggles.
 *
 * All API protocols are opt-in (default 0 in config.xml). An incoming request
 * for a disabled protocol gets a hard 404 here, before the security firewall
 * or any controller sees it. Cheaper than spinning up the resource pipeline,
 * and makes a disabled protocol indistinguishable from one that was never
 * deployed.
 *
 * Legacy SOAP / XML-RPC / JSON-RPC are gated separately inside
 * Maho_ApiPlatform_IndexController; legacy /api/rest is gated in public/api.php.
 *
 * Priority sits above StoreContextListener (100) so a disabled protocol short-
 * circuits before any store/auth work happens.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 200)]
class ProtocolToggleListener
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        $protocol = $this->resolveProtocol($path);
        if ($protocol === null) {
            return;
        }

        if (!\Mage::helper('apiplatform')->isProtocolEnabled($protocol)) {
            $event->setResponse(new Response(
                \Mage::helper('core')->jsonEncode([
                    'error' => 'protocol_disabled',
                    'message' => 'This API protocol is not enabled on this Maho instance.',
                ]),
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'application/json'],
            ));
        }
    }

    private function resolveProtocol(string $path): ?string
    {
        return match (true) {
            str_starts_with($path, '/api/admin/graphql') => Helper::PROTOCOL_ADMIN_GRAPHQL,
            str_starts_with($path, '/api/graphql') => Helper::PROTOCOL_GRAPHQL,
            // /api/docs is the OpenAPI / Swagger UI for the REST API, gating it
            // under REST v2 keeps the two in lockstep (no point documenting
            // endpoints that 404).
            str_starts_with($path, '/api/rest/v2'), str_starts_with($path, '/api/docs') => Helper::PROTOCOL_REST_V2,
            default => null,
        };
    }
}
