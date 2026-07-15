<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StoreProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Store
    {
        $storeCode = (string) ($uriVariables['storeCode'] ?? '');

        $store = $this->getStoreByCode($storeCode);
        if (!$store) {
            throw new NotFoundHttpException("Store with code '$storeCode' not found");
        }

        StoreContext::setStore((int) $store->getId());

        $dto = Store::fromModel($store);
        $dto->success = true;

        return $dto;
    }

    private function getStoreByCode(string $code): ?\Mage_Core_Model_Store
    {
        try {
            $store = \Mage::app()->getStore($code);
            if ($store && $store->getId() && $store->getIsActive()) {
                return $store;
            }
        } catch (\Mage_Core_Model_Store_Exception $e) {
            // Store not found
        }

        return null;
    }
}
