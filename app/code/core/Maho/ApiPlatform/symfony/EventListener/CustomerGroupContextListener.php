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
 * The listener runs at priority 5, after the firewall (8) and StoreContextAuthorizationListener (6),
 * so it knows the caller. It also clears the group on terminate. public/rest.php builds a new kernel
 * for each request and never resets the old kernel.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'reset')]
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
