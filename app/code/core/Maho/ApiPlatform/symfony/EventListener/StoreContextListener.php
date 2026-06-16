<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Mage_Core_Model_Store_Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Honors the `store` query parameter or `X-Store-Code` header by pointing
 * Maho's app at the matching store before any controller or authenticator runs.
 *
 * Priority 110 deliberately sits above AdminBridgeListener (105) and
 * IdempotencyListener (100): the store must be resolved before admin context
 * is bridged in (admin store-id resolution depends on it) and before the
 * idempotency key scope is computed.
 *
 * Authorization for the requested store is enforced by a second listener
 * (StoreContextAuthorizationListener) that runs after the firewall, this
 * listener captures the requested store but does not gate it.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 110)]
class StoreContextListener
{
    public const ATTR_REQUESTED_STORE_CODE = '_maho_requested_store_code';
    public const ATTR_RESOLVED_STORE_ID = '_maho_resolved_store_id';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $storeCode = $request->query->get('store') ?? $request->headers->get('X-Store-Code');
        if (!$storeCode) {
            return;
        }

        try {
            $store = \Mage::app()->getStore($storeCode);
            if ($store && $store->getId()) {
                \Mage::app()->setCurrentStore($store);
                $request->attributes->set(self::ATTR_REQUESTED_STORE_CODE, $storeCode);
                $request->attributes->set(self::ATTR_RESOLVED_STORE_ID, (int) $store->getId());
            }
        } catch (Mage_Core_Model_Store_Exception) {
            // Invalid store code, fall back to default
        }
    }
}
