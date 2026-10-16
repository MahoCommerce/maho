<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Gives the request the customer group of the caller, so prices, catalog rules and stock limits
 * read it instead of the customer session, which the stateless API never fills.
 *
 * Priority 5 sits below the firewall (8) and StoreContextAuthorizationListener (6), so the
 * caller is known.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class CustomerGroupContextListener implements ResetInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        \Mage::helper('customer')->setCurrentGroupId($this->getCustomerGroupId());
    }

    #[\Override]
    public function reset(): void
    {
        $this->customerGroupId = null;
        \Mage::helper('customer')->setCurrentGroupId(null);
    }
}
