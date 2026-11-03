<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StoreProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Store
    {
        /** @var \Mage_Core_Service_Store $service */
        $service = \Mage::getService('core/store');
        $store = $service->getActiveStore((string) ($uriVariables['storeCode'] ?? ''));

        // The target store arrives in the URI after the request-level allowlist
        // check ran, so it must be re-validated against the token here.
        $user = $this->security?->getUser();
        if ($user instanceof ApiUser && !$user->canAccessStore((int) $store->getId())) {
            throw new AccessDeniedHttpException('Token is not authorized for the requested store.');
        }

        StoreContext::setStore((int) $store->getId());

        $dto = Store::fromModel($store);
        $dto->success = true;

        return $dto;
    }
}
