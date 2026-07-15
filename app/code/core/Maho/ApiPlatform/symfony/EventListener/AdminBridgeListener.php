<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Mage_Core_Model_App_Area;
use Mage_Core_Model_Store_Exception;
use Maho\ApiPlatform\Security\AdminSessionAuthenticator;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bridges Maho's cookie-based admin session into the Symfony security context
 * for /api/admin/* endpoints by populating $_SERVER vars that
 * AdminSessionAuthenticator reads.
 *
 * Runs at high priority so the bridge is in place before the firewall fires.
 * Limited to /api/admin/* to keep the cost off public endpoints.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 105)]
class AdminBridgeListener
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/api/admin/')) {
            return;
        }

        \Mage::app()->loadAreaPart(
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
            Mage_Core_Model_App_Area::PART_EVENTS,
        );

        try {
            $input = (array) \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]');
        } catch (\JsonException) {
            $input = [];
        }

        $adminSession = \Mage::getSingleton('admin/session');
        if (!$adminSession->isLoggedIn()) {
            return;
        }

        $adminUser = $adminSession->getUser();
        if ($adminUser === null) {
            return;
        }

        $_SERVER['MAHO_ADMIN_USER_ID'] = $adminUser->getId();
        $_SERVER['MAHO_ADMIN_USERNAME'] = $adminUser->getUsername();
        $_SERVER['MAHO_STORE_ID'] = $this->resolveStoreId($input['variables']['storeId'] ?? null);
        $_SERVER['MAHO_IS_ADMIN'] = '1';
        $_SERVER['MAHO_API_BRIDGE_TOKEN'] = AdminSessionAuthenticator::generateBridgeToken(
            (string) $adminUser->getId(),
        );
    }

    /**
     * Resolve a client-supplied storeId to a real, active store. Falls back to
     * the default store view when the value is missing or doesn't match an
     * active store, admins must not be able to inject the admin scope (id 0)
     * or non-existent IDs simply by passing them in the GraphQL body.
     */
    private function resolveStoreId(mixed $requested): int
    {
        if ($requested !== null) {
            $requestedId = (int) $requested;
            if ($requestedId > 0) {
                try {
                    $store = \Mage::app()->getStore($requestedId);
                    if ($store->getId() && $store->getIsActive()) {
                        return (int) $store->getId();
                    }
                } catch (Mage_Core_Model_Store_Exception) {
                    // fall through to default
                }
            }
        }
        return StoreContext::getDefaultStoreId();
    }
}
